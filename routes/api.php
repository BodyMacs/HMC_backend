<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\BailleurController;
use App\Http\Controllers\Api\TenantController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/{id}', [PropertyController::class, 'show']);
Route::get('/home', [HomeController::class, 'index']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Properties Management
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::put('/properties/{id}', [PropertyController::class, 'update']);
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']);

    // ── Bailleur ────────────────────────────────────────────────────────
    Route::prefix('bailleur')->name('bailleur.')->group(function () {
        Route::get('/dashboard',   [BailleurController::class, 'dashboard']);
        Route::get('/properties',  [BailleurController::class, 'properties']);
        Route::get('/profile',     [BailleurController::class, 'profile']);
        Route::put('/profile',     [BailleurController::class, 'updateProfile']);
        Route::get('/applications', [BailleurController::class, 'applications']);
        Route::post('/applications/{id}/status', [BailleurController::class, 'updateApplicationStatus']);
        Route::get('/visits', [BailleurController::class, 'visits']);
        Route::post('/visits/{id}/status', [BailleurController::class, 'updateVisitStatus']);
        Route::get('/interventions', [BailleurController::class, 'interventions']);
        Route::post('/interventions/{id}/status', [BailleurController::class, 'updateInterventionStatus']);
    });

    // ── Locataire (Tenant) ──────────────────────────────────────────────
    Route::prefix('tenant')->name('tenant.')->group(function () {
        Route::get('/dashboard',     [TenantController::class, 'dashboard']);
        Route::get('/rentals',       [TenantController::class, 'rentals']);
        Route::get('/payments',      [TenantController::class, 'payments']);
        Route::get('/interventions', [TenantController::class, 'interventions']);
        Route::get('/favorites',     [TenantController::class, 'favorites']);
        Route::post('/favorites/toggle', [TenantController::class, 'toggleFavorite']);
        Route::post('/apply', [TenantController::class, 'apply']);
        Route::post('/book-visit', [TenantController::class, 'bookVisit']);
        Route::post('/interventions', [TenantController::class, 'createIntervention']);
        Route::get('/profile', [TenantController::class, 'profile']);
        Route::put('/profile', [TenantController::class, 'updateProfile']);
    });
});
