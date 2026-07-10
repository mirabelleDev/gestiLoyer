<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProprieteController;
use App\Http\Controllers\Api\PaiementController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Routes publiques - Inscription et Connexion
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    // Authentification
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/update-pin', [AuthController::class, 'updatePin']);
    Route::post('/verify-pin', [AuthController::class, 'verifyPin']);
    
    // Propriétés
    Route::get('/proprietes', [ProprieteController::class, 'index']);
    Route::post('/proprietes', [ProprieteController::class, 'store']);
    Route::get('/proprietes/{id}', [ProprieteController::class, 'show']);
    Route::put('/proprietes/{id}', [ProprieteController::class, 'update']);
    Route::delete('/proprietes/{id}', [ProprieteController::class, 'destroy']);
    
    // Paiements
    Route::get('/paiements', [PaiementController::class, 'index']);
    Route::post('/paiements', [PaiementController::class, 'store']);
    Route::get('/paiements/{id}', [PaiementController::class, 'show']);
    Route::get('/paiements-stats', [PaiementController::class, 'getStats']);
});