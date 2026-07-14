<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PreavisController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'PreavisController index fonctionne'
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'PreavisController store fonctionne'
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'PreavisController show fonctionne'
        ]);
    }

    public function approuver($id, Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'PreavisController approuver fonctionne'
        ]);
    }

    public function rejeter($id, Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'PreavisController rejeter fonctionne'
        ]);
    }
}