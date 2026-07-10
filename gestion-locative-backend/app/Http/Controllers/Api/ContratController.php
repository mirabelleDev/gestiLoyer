<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContratController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Contrat créé'
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => ['id' => $id]
        ]);
    }

    public function update(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Contrat mis à jour'
        ]);
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Contrat supprimé'
        ]);
    }

    public function paiements($id)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    public function resilier(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Contrat résilié'
        ]);
    }

    public function renouveler(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Contrat renouvelé'
        ]);
    }
}