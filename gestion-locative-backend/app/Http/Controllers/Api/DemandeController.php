<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DemandeController extends Controller
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
            'message' => 'Demande créée'
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
            'message' => 'Demande mise à jour'
        ]);
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée'
        ]);
    }

    public function traiter(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Demande en cours de traitement'
        ]);
    }

    public function resoudre(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Demande résolue'
        ]);
    }
}