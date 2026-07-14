<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Locataire;
use App\Models\UniteLocative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ContratController extends Controller
{
    // 3.1 LISTE DES CONTRATS
    public function index(Request $request)
    {
        $user = $request->user();
        
        $contrats = Contrat::with([
            'locataire', 
            'unite', 
            'unite.propriete',
            'paiements' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(3);
            }
        ])
        ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
            return $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        })
        ->when($user->profile_type === 'gerant', function($query) use ($user) {
            return $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        })
        ->when($user->profile_type === 'locataire', function($query) use ($user) {
            return $query->where('locataire_id', $user->profile_id);
        })
        ->orderBy('created_at', 'desc')
        ->get();

        // Ajouter des informations supplémentaires
        $contrats->map(function($contrat) {
            $contrat->est_expire = $contrat->est_expire;
            $contrat->mois_restants = $contrat->mois_restants;
            return $contrat;
        });

        return response()->json([
            'success' => true,
            'data' => $contrats
        ]);
    }

    // 3.2 CRÉER UN CONTRAT
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'locataire_id' => 'required|exists:locataires,id',
            'unite_locative_id' => 'required|exists:unites_locatives,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'duree_mois' => 'nullable|integer|min:1',
            'loyer_mensuel' => 'required|numeric|min:0',
            'devise' => 'nullable|string|max:10',
            'caution' => 'nullable|numeric|min:0',
            'avance_loyer' => 'nullable|numeric|min:0',
            'conditions_speciales' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifier l'unité
            $unite = UniteLocative::find($request->unite_locative_id);
            if ($unite->statut !== 'disponible') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette unité n\'est pas disponible'
                ], 400);
            }

            // Vérifier que le locataire n'a pas déjà un contrat actif
            $hasActive = Contrat::where('locataire_id', $request->locataire_id)
                ->where('statut', 'actif')
                ->exists();

            if ($hasActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce locataire a déjà un contrat actif'
                ], 400);
            }

            $contrat = Contrat::create([
                'locataire_id' => $request->locataire_id,
                'unite_locative_id' => $request->unite_locative_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'duree_mois' => $request->duree_mois ?? now()->diffInMonths($request->date_fin),
                'loyer_mensuel' => $request->loyer_mensuel,
                'devise' => $request->devise ?? 'XOF',
                'caution' => $request->caution ?? 0,
                'avance_loyer' => $request->avance_loyer ?? 0,
                'conditions_speciales' => $request->conditions_speciales,
                'statut' => 'actif',
                'is_active' => true
            ]);

            $unite->update(['statut' => 'occupe']);

            Log::info('Contrat créé', [
                'contrat_id' => $contrat->id,
                'locataire_id' => $request->locataire_id,
                'unite_id' => $request->unite_locative_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrat créé avec succès',
                'data' => $contrat->load(['locataire', 'unite', 'unite.propriete'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création contrat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du contrat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 3.3 DÉTAIL D'UN CONTRAT
    public function show($id)
    {
        $contrat = Contrat::with([
            'locataire',
            'unite',
            'unite.propriete',
            'paiements' => function($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->find($id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        // Statistiques du contrat
        $stats = [
            'total_paiements' => $contrat->paiements->sum('montant'),
            'paiements_effectues' => $contrat->paiements->count(),
            'dernier_paiement' => $contrat->paiements->first(),
            'mois_restants' => $contrat->mois_restants,
            'est_expire' => $contrat->est_expire
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'contrat' => $contrat,
                'statistiques' => $stats
            ]
        ]);
    }

    // 3.4 MODIFIER UN CONTRAT
    public function update(Request $request, $id)
    {
        $contrat = Contrat::find($id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'date_fin' => 'sometimes|date|after:date_debut',
            'loyer_mensuel' => 'sometimes|numeric|min:0',
            'caution' => 'nullable|numeric|min:0',
            'conditions_speciales' => 'nullable|string',
            'statut' => 'sometimes|in:actif,suspendu,resilie,termine',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Si le statut change, vérifier les implications
        if ($request->has('statut') && $request->statut === 'resilie') {
            $contrat->unite->update(['statut' => 'disponible']);
        }

        $contrat->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contrat mis à jour avec succès',
            'data' => $contrat
        ]);
    }

    // 3.5 SUPPRIMER UN CONTRAT
    public function destroy($id)
    {
        $contrat = Contrat::find($id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        // Libérer l'unité
        if ($contrat->statut === 'actif') {
            $contrat->unite->update(['statut' => 'disponible']);
        }
        
        $contrat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contrat supprimé avec succès'
        ]);
    }

    // 3.6 RÉSILIER UN CONTRAT
    public function resilier(Request $request, $id)
    {
        $contrat = Contrat::find($id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'motif_resiliation' => 'required|string',
            'date_resiliation' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contrat->update([
            'statut' => 'resilie',
            'date_resiliation' => $request->date_resiliation ?? now(),
            'motif_resiliation' => $request->motif_resiliation
        ]);

        // Libérer l'unité
        $contrat->unite->update(['statut' => 'disponible']);

        Log::info('Contrat résilié', [
            'contrat_id' => $contrat->id,
            'motif' => $request->motif_resiliation
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrat résilié avec succès',
            'data' => $contrat
        ]);
    }

    // 3.7 RENOUVELER UN CONTRAT
    public function renouveler(Request $request, $id)
    {
        $contrat = Contrat::find($id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        if ($contrat->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat actif peut être renouvelé'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'nouvelle_date_fin' => 'required|date|after:' . $contrat->date_fin,
            'nouveau_loyer' => 'nullable|numeric|min:0',
            'nouvelle_caution' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Créer un historique du contrat actuel
            $ancien = $contrat->replicate();
            $ancien->statut = 'termine';
            $ancien->save();

            // Renouveler le contrat
            $contrat->update([
                'date_debut' => $contrat->date_fin,
                'date_fin' => $request->nouvelle_date_fin,
                'loyer_mensuel' => $request->nouveau_loyer ?? $contrat->loyer_mensuel,
                'caution' => $request->nouvelle_caution ?? $contrat->caution,
                'duree_mois' => now()->diffInMonths($request->nouvelle_date_fin),
                'statut' => 'actif'
            ]);

            Log::info('Contrat renouvelé', [
                'contrat_id' => $contrat->id,
                'ancien_id' => $ancien->id,
                'nouvelle_date_fin' => $request->nouvelle_date_fin
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrat renouvelé avec succès',
                'data' => $contrat->load(['locataire', 'unite'])
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur renouvellement contrat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du renouvellement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 3.8 HISTORIQUE DES CONTRATS D'UN LOCATAIRE
    public function historique($locataireId)
    {
        $locataire = Locataire::find($locataireId);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        $contrats = $locataire->contrats()
            ->with(['unite', 'unite.propriete'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contrats
        ]);
    }

    // 3.9 PAIEMENTS D'UN CONTRAT
    public function paiements($id)
    {
        $contrat = Contrat::find($id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        $paiements = $contrat->paiements()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paiements
        ]);
    }
}