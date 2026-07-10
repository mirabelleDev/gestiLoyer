<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UniteLocativeController extends Controller
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
            'message' => 'Unité créée'
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
            'message' => 'Unité mise à jour'
        ]);
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Unité supprimée'
        ]);
    }

    public function contratActif($id)
    {
        return response()->json([
            'success' => true,
            'data' => null
        ]);
    }
}