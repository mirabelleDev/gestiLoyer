<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UniteLocative;
use App\Models\Propriete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UniteLocativeController extends Controller
{
    // Liste des unités
    public function index(Request $request)
    {
        $user = $request->user();
        
        $unites = UniteLocative::with(['propriete', 'contratActif', 'contratActif.locataire'])
            ->when($user->profile_type === 'gerant', function($query) use ($user) {
                return $query->whereHas('propriete', function($q) use ($user) {
                    $q->where('gerant_id', $user->profile_id);
                });
            })
            ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
                return $query->whereHas('propriete', function($q) use ($user) {
                    $q->where('proprietaire_id', $user->profile_id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $unites
        ]);
    }

    // Créer une unité
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propriete_id' => 'required|exists:proprietes,id',
            'numero' => 'required|string|max:50',
            'etage' => 'nullable|string|max:50',
            'type' => 'required|in:appartement,chambre,boutique,bureau,autre',
            'superficie' => 'nullable|numeric|min:0',
            'loyer_nominal' => 'required|numeric|min:0',
            'devise' => 'nullable|string|max:10',
            'statut' => 'required|in:disponible,occupe,travaux,reserve',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $unite = UniteLocative::create($request->all());

        // Mettre à jour le nombre d'unités de la propriété
        $propriete = Propriete::find($request->propriete_id);
        $propriete->increment('nombre_unites');

        return response()->json([
            'success' => true,
            'message' => 'Unité créée avec succès',
            'data' => $unite
        ], 201);
    }

    // Détail d'une unité
    public function show($id)
    {
        $unite = UniteLocative::with(['propriete', 'contrats', 'contrats.locataire', 'contrats.paiements'])
            ->find($id);

        if (!$unite) {
            return response()->json([
                'success' => false,
                'message' => 'Unité non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $unite
        ]);
    }

    // Mettre à jour une unité
    public function update(Request $request, $id)
    {
        $unite = UniteLocative::find($id);

        if (!$unite) {
            return response()->json([
                'success' => false,
                'message' => 'Unité non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'numero' => 'sometimes|string|max:50',
            'etage' => 'nullable|string|max:50',
            'type' => 'sometimes|in:appartement,chambre,boutique,bureau,autre',
            'superficie' => 'nullable|numeric|min:0',
            'loyer_nominal' => 'sometimes|numeric|min:0',
            'statut' => 'sometimes|in:disponible,occupe,travaux,reserve',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $unite->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Unité mise à jour avec succès',
            'data' => $unite
        ]);
    }

    // Supprimer une unité
    public function destroy($id)
    {
        $unite = UniteLocative::find($id);

        if (!$unite) {
            return response()->json([
                'success' => false,
                'message' => 'Unité non trouvée'
            ], 404);
        }

        // Vérifier si l'unité a des contrats actifs
        $hasActiveContract = $unite->contrats()->where('statut', 'actif')->exists();
        
        if ($hasActiveContract) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une unité avec un contrat actif'
            ], 400);
        }

        $unite->delete();

        // Décrémenter le nombre d'unités de la propriété
        $propriete = Propriete::find($unite->propriete_id);
        if ($propriete) {
            $propriete->decrement('nombre_unites');
        }

        return response()->json([
            'success' => true,
            'message' => 'Unité supprimée avec succès'
        ]);
    }

    // Contrat actif d'une unité
    public function contratActif($id)
    {
        $unite = UniteLocative::find($id);

        if (!$unite) {
            return response()->json([
                'success' => false,
                'message' => 'Unité non trouvée'
            ], 404);
        }

        $contrat = $unite->contratActif()->with(['locataire'])->first();

        return response()->json([
            'success' => true,
            'data' => $contrat
        ]);
    }
}