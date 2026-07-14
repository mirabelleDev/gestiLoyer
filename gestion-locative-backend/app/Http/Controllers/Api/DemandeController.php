<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Locataire;
use App\Models\UniteLocative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DemandeController extends Controller
{
    // 7.1 LISTE DES DEMANDES
    public function index(Request $request)
    {
        $user = $request->user();
        
        $demandes = Demande::with(['locataire', 'unite', 'unite.propriete', 'traitePar'])
            ->when($user->profile_type === 'locataire', function($query) use ($user) {
                return $query->where('locataire_id', $user->profile_id);
            })
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
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $demandes
        ]);
    }

    // 7.2 CRÉER UNE DEMANDE
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->profile_type !== 'locataire') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les locataires peuvent créer des demandes'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'unite_locative_id' => 'required|exists:unites_locatives,id',
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:reparation,entretien,plainte,autre',
            'photo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que le locataire est associé à cette unité
        $locataire = Locataire::find($user->profile_id);
        $hasContract = $locataire->contrats()
            ->where('unite_locative_id', $request->unite_locative_id)
            ->where('statut', 'actif')
            ->exists();

        if (!$hasContract) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas locataire de cette unité'
            ], 403);
        }

        $demande = Demande::create([
            'locataire_id' => $user->profile_id,
            'unite_locative_id' => $request->unite_locative_id,
            'titre' => $request->titre,
            'description' => $request->description,
            'type' => $request->type,
            'photo' => $request->photo,
            'statut' => 'en_attente',
            'is_active' => true
        ]);

        // Notification au propriétaire/gérant
        $this->notifierDemande($demande);

        return response()->json([
            'success' => true,
            'message' => 'Demande créée avec succès',
            'data' => $demande->load(['locataire', 'unite'])
        ], 201);
    }

    // 7.3 DÉTAIL D'UNE DEMANDE
    public function show($id)
    {
        $demande = Demande::with(['locataire', 'unite', 'unite.propriete', 'traitePar'])
            ->find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $demande
        ]);
    }

    // 7.4 MODIFIER UNE DEMANDE
    public function update(Request $request, $id)
    {
        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $user = $request->user();

        // Seul le créateur peut modifier sa demande
        if ($user->profile_type === 'locataire' && $demande->locataire_id !== $user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette demande'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:reparation,entretien,plainte,autre',
            'photo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $demande->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Demande mise à jour avec succès',
            'data' => $demande
        ]);
    }

    // 7.5 SUPPRIMER UNE DEMANDE
    public function destroy($id, Request $request)
    {
        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $user = $request->user();

        // Vérifier les permissions
        if ($user->profile_type === 'locataire' && $demande->locataire_id !== $user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette demande'
            ], 403);
        }

        $demande->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée avec succès'
        ]);
    }

    // 7.6 TRAITER UNE DEMANDE
    public function traiter(Request $request, $id)
    {
        $user = $request->user();
        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        // Vérifier les droits
        if ($user->profile_type === 'proprietaire') {
            $hasAccess = $demande->unite->propriete->proprietaire_id === $user->profile_id;
        } elseif ($user->profile_type === 'gerant') {
            $hasAccess = $demande->unite->propriete->gerant_id === $user->profile_id;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour traiter cette demande'
            ], 403);
        }

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à traiter cette demande'
            ], 403);
        }

        if ($demande->statut === 'resolu' || $demande->statut === 'rejete') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est déjà traitée'
            ], 400);
        }

        $demande->update([
            'statut' => 'en_cours',
            'traite_par' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande en cours de traitement',
            'data' => $demande
        ]);
    }

    // 7.7 RÉSOUDRE UNE DEMANDE
    public function resoudre(Request $request, $id)
    {
        $user = $request->user();
        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'commentaire_resolution' => 'required|string',
            'statut' => 'required|in:resolu,rejete'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier les droits
        if ($user->profile_type === 'proprietaire') {
            $hasAccess = $demande->unite->propriete->proprietaire_id === $user->profile_id;
        } elseif ($user->profile_type === 'gerant') {
            $hasAccess = $demande->unite->propriete->gerant_id === $user->profile_id;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour résoudre cette demande'
            ], 403);
        }

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à résoudre cette demande'
            ], 403);
        }

        $demande->update([
            'statut' => $request->statut,
            'date_resolution' => now(),
            'commentaire_resolution' => $request->commentaire_resolution,
            'traite_par' => $user->id
        ]);

        // Notifier le locataire
        $this->notifierResolution($demande);

        return response()->json([
            'success' => true,
            'message' => 'Demande ' . ($request->statut === 'resolu' ? 'résolue' : 'rejetée') . ' avec succès',
            'data' => $demande
        ]);
    }

    // 7.8 STATISTIQUES DES DEMANDES
    public function stats(Request $request)
    {
        $user = $request->user();

        $query = Demande::query();

        if ($user->profile_type === 'locataire') {
            $query->where('locataire_id', $user->profile_id);
        } elseif ($user->profile_type === 'proprietaire') {
            $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        $stats = [
            'total' => $query->count(),
            'en_attente' => $query->where('statut', 'en_attente')->count(),
            'en_cours' => $query->where('statut', 'en_cours')->count(),
            'resolues' => $query->where('statut', 'resolu')->count(),
            'rejetees' => $query->where('statut', 'rejete')->count(),
            'temps_moyen' => $query->whereNotNull('date_resolution')
                ->get()
                ->avg(function($d) {
                    return $d->created_at->diffInDays($d->date_resolution);
                }) ?? 0,
            'par_type' => $query->select('type', DB::raw('count(*) as total'))
                ->groupBy('type')
                ->get()
                ->pluck('total', 'type')
                ->toArray()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // 7.9 NOTIFIER LE PROPRIÉTAIRE D'UNE NOUVELLE DEMANDE
    private function notifierDemande($demande)
    {
        // Simuler la notification
        Log::info('Nouvelle demande créée', [
            'demande_id' => $demande->id,
            'locataire' => $demande->locataire->full_name,
            'type' => $demande->type,
            'titre' => $demande->titre
        ]);
    }

    // 7.10 NOTIFIER LE LOCATAIRE DE LA RÉSOLUTION
    private function notifierResolution($demande)
    {
        Log::info('Demande résolue', [
            'demande_id' => $demande->id,
            'locataire' => $demande->locataire->full_name,
            'statut' => $demande->statut,
            'commentaire' => $demande->commentaire_resolution
        ]);
    }
}