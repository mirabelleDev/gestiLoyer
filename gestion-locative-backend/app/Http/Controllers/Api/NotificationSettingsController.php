<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'index notification settings fonctionne'
        ]);
    }

    public function update(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'update notification settings fonctionne'
        ]);
    }

    public function reset(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'reset notification settings fonctionne'
        ]);
    }

    public function test(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'test notification settings fonctionne'
        ]);
    }

    public function stats(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'stats notification settings fonctionne'
        ]);
    }
}