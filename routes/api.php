<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\BailleurController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\PrestataireController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\FormationController;

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
Route::get('/marketplace/items', [MarketplaceController::class, 'index']);
Route::get('/marketplace/items/{id}', [MarketplaceController::class, 'show']);
Route::get('/marketplace/categories', [MarketplaceController::class, 'categories']);

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
        Route::get('/finances', [BailleurController::class, 'finances']);
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
        Route::get('/profile', [TenantController::class, 'profile']);
        Route::put('/profile', [TenantController::class, 'updateProfile']);
    });

    // ── Agent ───────────────────────────────────────────────────────────
    Route::prefix('agent')->name('agent.')->group(function () {
        Route::get('/dashboard',   [App\Http\Controllers\Api\AgentController::class, 'dashboard']);
        Route::get('/properties',  [App\Http\Controllers\Api\AgentController::class, 'properties']);
        Route::get('/clients',     [App\Http\Controllers\Api\AgentController::class, 'clients']);
        Route::get('/missions',    [App\Http\Controllers\Api\AgentController::class, 'missions']);
        Route::get('/agenda',      [App\Http\Controllers\Api\AgentController::class, 'agenda']);
        Route::get('/stats',       [App\Http\Controllers\Api\AgentController::class, 'stats']);
        Route::get('/formations',  [FormationController::class, 'myFormations']);
    });

    // ── Formations ──────────────────────────────────────────────────────
    Route::get('/formations', [FormationController::class, 'index']);
    Route::post('/formations/{formation}/purchase', [FormationController::class, 'purchase']);
    Route::get('/formations/{formation}', [FormationController::class, 'show']);

    // ── Prestataire ─────────────────────────────────────────────────────
    Route::prefix('prestataire')->name('prestataire.')->group(function () {
        Route::get('/dashboard',     [PrestataireController::class, 'dashboard']);
        Route::get('/services',      [PrestataireController::class, 'services']);
        Route::get('/interventions', [PrestataireController::class, 'interventions']);
        Route::get('/agenda',        [PrestataireController::class, 'agenda']);
        Route::get('/finances',      [PrestataireController::class, 'finances']);
    });

    // ── Admin ───────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard',    [AdminController::class, 'dashboard']);
        Route::get('/users',        [AdminController::class, 'users']);
        Route::put('/users/{user}', [AdminController::class, 'updateUserStatus']);
        Route::get('/properties',   [AdminController::class, 'properties']);
        Route::put('/properties/{property}', [AdminController::class, 'updatePropertyStatus']);
        Route::get('/finances',     [AdminController::class, 'finances']);
        Route::get('/services',     [AdminController::class, 'services']);
    });
});
