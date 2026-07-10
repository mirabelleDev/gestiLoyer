<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LocataireController extends Controller
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
            'message' => 'Locataire créé'
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
            'message' => 'Locataire mis à jour'
        ]);
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Locataire supprimé'
        ]);
    }

    public function contrats($id)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    public function paiements($id)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }
}