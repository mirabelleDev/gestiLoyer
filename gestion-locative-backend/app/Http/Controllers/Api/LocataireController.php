<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Locataire;
use App\Models\User;
use App\Models\Contrat;
use App\Models\UniteLocative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LocataireController extends Controller
{
    // 2.1 LISTE DES LOCATAIRES
    public function index(Request $request)
    {
        $user = $request->user();
        
        $locataires = Locataire::with([
            'contrats' => function($query) {
                $query->where('statut', 'actif')->orWhere('statut', 'suspendu');
            },
            'contrats.unite',
            'contrats.unite.propriete',
            'paiements' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            }
        ])
        ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
            return $query->whereHas('contrats.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        })
        ->when($user->profile_type === 'gerant', function($query) use ($user) {
            return $query->whereHas('contrats.unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        })
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $locataires
        ]);
    }

    // 2.2 CRÉER UN LOCATAIRE
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:locataires,email',
            'telephone' => 'required|string|max:20|unique:locataires,telephone',
            'telephone_urgence' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'adresse' => 'nullable|string',
            'piece_identite' => 'nullable|string',
            'numero_piece' => 'nullable|string',
            'create_user' => 'boolean',
            'password' => 'required_if:create_user,true|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Créer le locataire
            $locataire = Locataire::create($request->except(['password', 'create_user']));

            // Si création d'utilisateur demandée
            if ($request->create_user && $request->has('password')) {
                $user = User::create([
                    'name' => $request->nom . ($request->prenom ? ' ' . $request->prenom : ''),
                    'email' => $request->email,
                    'phone' => $request->telephone,
                    'password' => Hash::make($request->password),
                    'profile_type' => 'App\\Models\\Locataire',
                    'profile_id' => $locataire->id,
                    'is_active' => true
                ]);

                Log::info('Utilisateur créé pour le locataire', [
                    'locataire_id' => $locataire->id,
                    'user_id' => $user->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Locataire créé avec succès',
                'data' => $locataire->load('user')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création locataire: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du locataire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 2.3 DÉTAIL D'UN LOCATAIRE
    public function show($id)
    {
        $locataire = Locataire::with([
            'contrats' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'contrats.unite',
            'contrats.unite.propriete',
            'paiements' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'demandes' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            }
        ])->find($id);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $locataire
        ]);
    }

    // 2.4 MODIFIER UN LOCATAIRE
    public function update(Request $request, $id)
    {
        $locataire = Locataire::find($id);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:locataires,email,' . $id,
            'telephone' => 'sometimes|string|max:20|unique:locataires,telephone,' . $id,
            'telephone_urgence' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'adresse' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $locataire->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Locataire mis à jour avec succès',
            'data' => $locataire
        ]);
    }

    // 2.5 SUPPRIMER UN LOCATAIRE
    public function destroy($id)
    {
        $locataire = Locataire::find($id);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        $hasActiveContract = $locataire->contrats()->where('statut', 'actif')->exists();
        
        if ($hasActiveContract) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un locataire avec un contrat actif'
            ], 400);
        }

        $locataire->delete();

        return response()->json([
            'success' => true,
            'message' => 'Locataire supprimé avec succès'
        ]);
    }

    // 2.6 ASSOCIER LOCATAIRE À UNE PROPRIÉTÉ
    public function associerPropriete(Request $request, $id)
    {
        $locataire = Locataire::find($id);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'unite_locative_id' => 'required|exists:unites_locatives,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'loyer_mensuel' => 'required|numeric|min:0',
            'caution' => 'nullable|numeric|min:0',
            'avance_loyer' => 'nullable|numeric|min:0',
            'devise' => 'nullable|string|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier l'unité
        $unite = UniteLocative::find($request->unite_locative_id);
        if ($unite->statut !== 'disponible') {
            return response()->json([
                'success' => false,
                'message' => 'Cette unité n\'est pas disponible'
            ], 400);
        }

        try {
            // Créer le contrat
            $contrat = Contrat::create([
                'locataire_id' => $locataire->id,
                'unite_locative_id' => $request->unite_locative_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'duree_mois' => now()->diffInMonths($request->date_fin),
                'loyer_mensuel' => $request->loyer_mensuel,
                'devise' => $request->devise ?? 'XOF',
                'caution' => $request->caution ?? 0,
                'avance_loyer' => $request->avance_loyer ?? 0,
                'statut' => 'actif',
                'is_active' => true
            ]);

            // Mettre à jour l'unité
            $unite->update(['statut' => 'occupe']);

            return response()->json([
                'success' => true,
                'message' => 'Locataire associé à la propriété avec succès',
                'data' => $contrat->load(['locataire', 'unite', 'unite.propriete'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'association',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 2.7 CONTRATS D'UN LOCATAIRE
    public function contrats($id)
    {
        $locataire = Locataire::find($id);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        $contrats = $locataire->contrats()
            ->with(['unite', 'unite.propriete', 'paiements'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contrats
        ]);
    }

    // 2.8 PAIEMENTS D'UN LOCATAIRE
    public function paiements($id)
    {
        $locataire = Locataire::find($id);

        if (!$locataire) {
            return response()->json([
                'success' => false,
                'message' => 'Locataire non trouvé'
            ], 404);
        }

        $paiements = $locataire->paiements()
            ->with(['contrat', 'contrat.unite'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paiements
        ]);
    }
}