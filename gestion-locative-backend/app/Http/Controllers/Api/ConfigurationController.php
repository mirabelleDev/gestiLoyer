<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->configuration
        ]);
    }

    public function update(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Configuration mise à jour'
        ]);
    }

    public function uploadLogo(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Logo téléchargé'
        ]);
    }

    public function uploadSignature(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Signature téléchargée'
        ]);
    }
}