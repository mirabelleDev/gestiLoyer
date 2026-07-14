<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propriete;
use App\Models\UniteLocative;
use App\Models\Contrat;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProprieteController extends Controller
{
    // 1.1 LISTE DES PROPRIÉTÉS
    public function index(Request $request)
    {
        $user = $request->user();
        
        $proprietes = Propriete::with(['gerant', 'proprietaire', 'unites', 'unites.contratActif.locataire'])
            ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
                return $query->where('proprietaire_id', $user->profile_id);
            })
            ->when($user->profile_type === 'gerant', function($query) use ($user) {
                return $query->where('gerant_id', $user->profile_id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($propriete) {
                $propriete->taux_occupation = $propriete->taux_occupation;
                $propriete->unites_libres = $propriete->unites_libres;
                return $propriete;
            });

        return response()->json([
            'success' => true,
            'data' => $proprietes
        ]);
    }

    // 1.2 CRÉER UNE PROPRIÉTÉ
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'adresse' => 'required|string',
            'ville' => 'nullable|string',
            'quartier' => 'nullable|string',
            'type' => 'required|in:immeuble,villa,residence,autre',
            'description' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        
        if ($user->profile_type === 'proprietaire') {
            $data['proprietaire_id'] = $user->profile_id;
        } elseif ($user->profile_type === 'gerant') {
            $data['gerant_id'] = $user->profile_id;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour créer une propriété'
            ], 403);
        }

        $propriete = Propriete::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Propriété créée avec succès',
            'data' => $propriete->load(['gerant', 'proprietaire'])
        ], 201);
    }

    // 1.3 DÉTAIL D'UNE PROPRIÉTÉ
    public function show($id)
    {
        $propriete = Propriete::with([
            'gerant', 
            'proprietaire', 
            'unites', 
            'unites.contratActif.locataire',
            'unites.contrats.paiements'
        ])->find($id);

        if (!$propriete) {
            return response()->json([
                'success' => false,
                'message' => 'Propriété non trouvée'
            ], 404);
        }

        // Statistiques de la propriété
        $stats = [
            'total_unites' => $propriete->unites->count(),
            'unites_occupees' => $propriete->unites->where('statut', 'occupe')->count(),
            'unites_libres' => $propriete->unites->where('statut', 'disponible')->count(),
            'taux_occupation' => $propriete->taux_occupation,
            'revenus_mois' => $this->getRevenusMois($propriete->id),
            'revenus_total' => $this->getRevenusTotal($propriete->id),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'propriete' => $propriete,
                'statistiques' => $stats
            ]
        ]);
    }

    // 1.4 MODIFIER UNE PROPRIÉTÉ
    public function update(Request $request, $id)
    {
        $propriete = Propriete::find($id);

        if (!$propriete) {
            return response()->json([
                'success' => false,
                'message' => 'Propriété non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'adresse' => 'sometimes|string',
            'ville' => 'nullable|string',
            'quartier' => 'nullable|string',
            'type' => 'sometimes|in:immeuble,villa,residence,autre',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $propriete->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Propriété mise à jour avec succès',
            'data' => $propriete
        ]);
    }

    // 1.5 SUPPRIMER UNE PROPRIÉTÉ
    public function destroy($id)
    {
        $propriete = Propriete::find($id);

        if (!$propriete) {
            return response()->json([
                'success' => false,
                'message' => 'Propriété non trouvée'
            ], 404);
        }

        $hasOccupied = UniteLocative::where('propriete_id', $id)
            ->where('statut', 'occupe')
            ->exists();

        if ($hasOccupied) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une propriété avec des unités occupées'
            ], 400);
        }

        $propriete->delete();

        return response()->json([
            'success' => true,
            'message' => 'Propriété supprimée avec succès'
        ]);
    }

    // 1.6 HISTORIQUE D'UNE PROPRIÉTÉ
    public function historique($id)
    {
        $propriete = Propriete::find($id);

        if (!$propriete) {
            return response()->json([
                'success' => false,
                'message' => 'Propriété non trouvée'
            ], 404);
        }

        $historique = [
            'propriete' => $propriete->only(['id', 'nom', 'adresse']),
            'contrats' => Contrat::whereHas('unite', function($q) use ($id) {
                $q->where('propriete_id', $id);
            })
            ->with(['locataire', 'unite'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($contrat) {
                return [
                    'id' => $contrat->id,
                    'locataire' => $contrat->locataire->full_name,
                    'unite' => $contrat->unite->numero,
                    'date_debut' => $contrat->date_debut->format('d/m/Y'),
                    'date_fin' => $contrat->date_fin ? $contrat->date_fin->format('d/m/Y') : null,
                    'statut' => $contrat->statut,
                    'loyer_mensuel' => $contrat->loyer_mensuel
                ];
            }),
            'paiements' => Paiement::whereHas('contrat.unite', function($q) use ($id) {
                $q->where('propriete_id', $id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($paiement) {
                return [
                    'id' => $paiement->id,
                    'montant' => $paiement->montant,
                    'mode' => $paiement->mode_paiement,
                    'date' => $paiement->date_paiement->format('d/m/Y'),
                    'locataire' => $paiement->locataire->full_name
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $historique
        ]);
    }

    private function getRevenusMois($proprieteId)
    {
        return Paiement::whereHas('contrat.unite', function($q) use ($proprieteId) {
            $q->where('propriete_id', $proprieteId);
        })
        ->whereMonth('date_paiement', now()->month)
        ->whereYear('date_paiement', now()->year)
        ->sum('montant');
    }

    private function getRevenusTotal($proprieteId)
    {
        return Paiement::whereHas('contrat.unite', function($q) use ($proprieteId) {
            $q->where('propriete_id', $proprieteId);
        })->sum('montant');
    }
}