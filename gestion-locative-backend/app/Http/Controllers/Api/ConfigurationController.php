<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ConfigurationController extends Controller
{
    // Récupérer la configuration
    public function index(Request $request)
    {
        $user = $request->user();
        $config = $user->configuration;

        if (!$config) {
            // Créer une configuration par défaut
            $config = Configuration::create([
                'user_id' => $user->id,
                'devise' => 'XOF',
                'niveau_abonnement' => 'decouverte',
                'quota_proprietes' => 10,
                'quota_locataires' => 50,
                'auto_lock_time' => 5,
                'is_active' => true
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    // Mettre à jour la configuration
    public function update(Request $request)
    {
        $user = $request->user();
        $config = $user->configuration;

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom_entreprise' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string',
            'devise' => 'nullable|string|max:10',
            'auto_lock_time' => 'nullable|integer|min:1|max:60',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $config->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Configuration mise à jour avec succès',
            'data' => $config
        ]);
    }

    // Upload du logo
    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $config = $user->configuration;

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration non trouvée'
            ], 404);
        }

        // Supprimer l'ancien logo
        if ($config->logo && Storage::exists($config->logo)) {
            Storage::delete($config->logo);
        }

        // Upload du nouveau logo
        $path = $request->file('logo')->store('logos', 'public');
        $config->update(['logo' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Logo téléchargé avec succès',
            'data' => ['logo_url' => Storage::url($path)]
        ]);
    }

    // Upload de la signature
    public function uploadSignature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'signature' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $config = $user->configuration;

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration non trouvée'
            ], 404);
        }

        // Supprimer l'ancienne signature
        if ($config->signature && Storage::exists($config->signature)) {
            Storage::delete($config->signature);
        }

        // Upload de la nouvelle signature
        $path = $request->file('signature')->store('signatures', 'public');
        $config->update(['signature' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Signature téléchargée avec succès',
            'data' => ['signature_url' => Storage::url($path)]
        ]);
    }
}