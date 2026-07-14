<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Locataire;
use App\Models\Gerant;
use App\Models\Proprietaire;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'profile_type' => 'required|in:locataire,gerant,proprietaire',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $profile = null;
            $profileData = [
                'nom' => $request->name,
                'email' => $request->email,
                'telephone' => $request->phone,
                'is_active' => true
            ];

            switch ($request->profile_type) {
                case 'locataire':
                    $profile = Locataire::create($profileData);
                    break;
                case 'gerant':
                    $profile = Gerant::create($profileData);
                    break;
                case 'proprietaire':
                    $profile = Proprietaire::create($profileData);
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Type de profil invalide'
                    ], 400);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'profile_type' => 'App\\Models\\' . ucfirst($request->profile_type),
                'profile_id' => $profile->id,
                'is_active' => true
            ]);

            Configuration::create([
                'user_id' => $user->id,
                'devise' => 'XOF',
                'quota_proprietes' => 10,
                'quota_locataires' => 50,
                'niveau_abonnement' => 'decouverte'
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'data' => [
                    'user' => $user->load('profile'),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé'
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => $user->load('profile', 'configuration'),
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('profile', 'configuration')
        ]);
    }

    public function updatePin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin_code' => 'required|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->pin_code = Hash::make($request->pin_code);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Code PIN mis à jour avec succès'
        ]);
    }

    public function verifyPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!$user->pin_code || !Hash::check($request->pin_code, $user->pin_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Code PIN incorrect'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code PIN vérifié avec succès'
        ]);
    }
}