<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaiementMobileController extends Controller
{
    public function initierPaiement(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'initierPaiement fonctionne'
        ]);
    }

    public function verifierStatut($reference)
    {
        return response()->json([
            'success' => true,
            'message' => 'verifierStatut fonctionne'
        ]);
    }

    public function webhookConfirmation(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'webhookConfirmation fonctionne'
        ]);
    }

    public function historique(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'historique fonctionne'
        ]);
    }
}