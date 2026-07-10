<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propriete;
use App\Models\UniteLocative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProprieteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $proprietes = Propriete::with(['gerant', 'proprietaire', 'unites'])
            ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
                return $query->where('proprietaire_id', $user->profile_id);
            })
            ->when($user->profile_type === 'gerant', function($query) use ($user) {
                return $query->where('gerant_id', $user->profile_id);
            })
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $proprietes
        ]);
    }

    public function store(Request $request)
    {
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

        $user = $request->user();
        
        $data = $request->all();
        if ($user->profile_type === 'proprietaire') {
            $data['proprietaire_id'] = $user->profile_id;
        } elseif ($user->profile_type === 'gerant') {
            $data['gerant_id'] = $user->profile_id;
        }

        $propriete = Propriete::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Propriété créée avec succès',
            'data' => $propriete
        ], 201);
    }

    public function show($id)
    {
        $propriete = Propriete::with(['gerant', 'proprietaire', 'unites.locataires'])->find($id);

        if (!$propriete) {
            return response()->json([
                'success' => false,
                'message' => 'Propriété non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $propriete
        ]);
    }

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

    public function destroy($id)
    {
        $propriete = Propriete::find($id);

        if (!$propriete) {
            return response()->json([
                'success' => false,
                'message' => 'Propriété non trouvée'
            ], 404);
        }

        $propriete->delete();

        return response()->json([
            'success' => true,
            'message' => 'Propriété supprimée avec succès'
        ]);
    }
}
