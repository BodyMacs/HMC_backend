<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BailleurController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PrestataireController;
use App\Http\Controllers\Api\ProspectController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\RentalApplicationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\VisiteController;

/*
|--------------------------------------------------------------------------
| API Routes — HomeCameroon
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;

// ── Routes Publiques ──────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/{id}', [PropertyController::class, 'show']);
Route::get('/home', [HomeController::class, 'index']);
Route::get('/marketplace/items', [MarketplaceController::class, 'index']);
Route::get('/marketplace/items/{id}', [MarketplaceController::class, 'show']);
Route::get('/marketplace/categories', [MarketplaceController::class, 'categories']);

// NotchPay Callback
Route::get('/notchpay/callback', [PaymentController::class, 'callback'])->name('notchpay.callback');
Route::post('/notchpay/webhook', [PaymentController::class, 'webhook'])->name('notchpay.webhook');
Route::get('/transactions/{reference}/status', [PaymentController::class, 'getTransactionStatus']);

// ── Routes Protégées (auth:sanctum) ───────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // ── Gestion des Rôles ────────────────────────────────────────────────────
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles/switch', [RoleController::class, 'switch']);
    Route::post('/roles/acquire', [RoleController::class, 'acquire']);

    // ── Properties (gestion CRUD) ────────────────────────────────────────────
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::put('/properties/{id}', [PropertyController::class, 'update']);
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']);

    // ════════════════════════════════════════════════════════════════════════
    // PROCESSUS LOCATIF — VISITES (accessible à tout user authentifié)
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('visits')->name('visits.')->group(function (): void {
        Route::get('/', [VisiteController::class, 'myVisits']);          // Mes visites
        Route::post('/', [VisiteController::class, 'book']);              // Réserver une visite
        Route::post('/{id}/confirm', [VisiteController::class, 'userConfirm']); // User confirme
        Route::post('/{id}/cancel', [VisiteController::class, 'cancel']); // Annuler
    });

    // ════════════════════════════════════════════════════════════════════════
    // PROCESSUS LOCATIF — DOSSIERS (accessible à tout user authentifié)
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('rental-applications')->name('applications.')->group(function (): void {
        Route::get('/', [RentalApplicationController::class, 'myApplications']);  // Mes dossiers
        Route::post('/', [RentalApplicationController::class, 'submit']);          // Soumettre un dossier
        Route::get('/{id}', [RentalApplicationController::class, 'show']);         // Détail
        Route::post('/{id}/sign', [RentalApplicationController::class, 'signContract']); // Signer contrat
    });

    // ════════════════════════════════════════════════════════════════════════
    // PROSPECT (client cherchant à louer) — 3 phases du processus locatif
    // Routes consolidées dans ProspectController (les routes /visits et
    // /rental-applications ci-dessus couvrent la même logique via
    // VisiteController et RentalApplicationController)
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('prospect')->name('prospect.')->group(function (): void {
        // Phase 1 : Visites
        Route::get('/visits',                [ProspectController::class, 'myVisits']);
        Route::post('/visits',               [ProspectController::class, 'bookVisit']);
        Route::post('/visits/{id}/pay',      [ProspectController::class, 'payVisitFee']);
        Route::post('/visits/{id}/confirm',  [ProspectController::class, 'confirmVisit']);
        Route::post('/visits/{id}/cancel',   [ProspectController::class, 'cancelVisit']);

        // Phase 2 : Dossiers
        Route::get('/applications',          [ProspectController::class, 'myApplications']);
        Route::post('/applications',         [ProspectController::class, 'createApplication']);
        Route::get('/applications/{id}',     [ProspectController::class, 'showApplication']);
        Route::put('/applications/{id}',     [ProspectController::class, 'updateApplication']);

        // Phase 3 : Suivi paiement
        Route::get('/rentals',               [ProspectController::class, 'myRentals']);
        Route::get('/rentals/{id}',          [ProspectController::class, 'showRental']);
        Route::post('/rentals/{id}/pay-initial', [ProspectController::class, 'payInitial']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // BAILLEUR — Observateur du processus locatif (LECTURE SEULE)
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('bailleur')->name('bailleur.')->group(function (): void {
        Route::get('/dashboard',    [BailleurController::class, 'dashboard']);
        Route::get('/properties',   [BailleurController::class, 'properties']);
        Route::get('/profile',      [BailleurController::class, 'profile']);
        Route::put('/profile',      [BailleurController::class, 'updateProfile']);
        Route::get('/visits',       [BailleurController::class, 'visits']);          // Lecture seule
        Route::get('/interventions', [BailleurController::class, 'interventions']);
        Route::post('/interventions/{id}/status', [BailleurController::class, 'updateInterventionStatus']);
        Route::get('/finances',     [BailleurController::class, 'finances']);
        Route::post('/rentals',     [BailleurController::class, 'createRental']);

        // Suivi du processus locatif — LECTURE SEULE
        // Le bailleur ne peut QUE consulter, pas agir
        Route::get('/properties/{id}/rental-status', [BailleurController::class, 'propertyRentalStatus']);

        // — Demandes de publication (Audit)
        Route::get('/publication-requests',  [BailleurController::class, 'myPublicationRequests']);
        Route::post('/publication-requests', [BailleurController::class, 'submitPublicationRequest']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // LOCATAIRE (tenant) — Disponible après obtention du rôle locataire
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('tenant')->name('tenant.')->group(function (): void {
        Route::get('/dashboard',          [TenantController::class, 'dashboard']);
        Route::get('/rentals',            [TenantController::class, 'rentals']);
        Route::get('/payments',           [TenantController::class, 'payments']);
        Route::get('/interventions',      [TenantController::class, 'interventions']);
        Route::get('/favorites',          [TenantController::class, 'favorites']);
        Route::post('/favorites/toggle',  [TenantController::class, 'toggleFavorite']);
        Route::post('/interventions',     [TenantController::class, 'createIntervention']);
        Route::get('/profile',            [TenantController::class, 'profile']);
        Route::put('/profile',            [TenantController::class, 'updateProfile']);

        // Anciennes routes (gardées pour compatibilité — à déprécier progressivement)
        Route::post('/book-visit', [TenantController::class, 'bookVisit']);
        Route::post('/apply',      [TenantController::class, 'apply']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // AGENT HMC — Gestion du processus locatif complet
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('agent')->name('agent.')->group(function (): void {
        Route::get('/dashboard',    [AgentController::class, 'dashboard']);
        Route::get('/properties',   [AgentController::class, 'properties']);
        Route::get('/clients',      [AgentController::class, 'clients']);
        Route::get('/missions',     [AgentController::class, 'missions']);
        Route::get('/agenda',       [AgentController::class, 'agenda']);

        // Phase 1 : Visites
        Route::get('/visits',                     [AgentController::class, 'visits']);
        Route::post('/visits/{id}/confirm',        [AgentController::class, 'confirmVisit']);
        Route::post('/visits/{id}/cancel',         [AgentController::class, 'cancelVisit']);

        // Phase 2 : Dossiers
        Route::get('/applications',               [AgentController::class, 'applications']);
        Route::get('/applications/{id}',           [AgentController::class, 'showApplication']);
        Route::post('/applications/{id}/validate', [AgentController::class, 'validateApplication']);
        Route::post('/applications/{id}/reject',   [AgentController::class, 'rejectApplication']);

        // Phase 3 : Confirmation paiement → attribution rôle locataire
        Route::get('/rentals',                    [AgentController::class, 'rentals']);
        Route::post('/rentals/{id}/confirm-payment', [AgentController::class, 'confirmPayment']);

        // Formations agent
        Route::get('/formations', [FormationController::class, 'myFormations']);

        // Missions d'audit (Publication)
        Route::get('/publication-missions',              [AgentController::class, 'publicationMissions']);
        Route::get('/publication-missions/{id}',         [AgentController::class, 'showPublicationMission']);
        Route::post('/publication-missions/{id}/schedule', [AgentController::class, 'schedulePublicationMission']);
        Route::post('/publication-missions/{id}/complete', [AgentController::class, 'completePublication']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // FORMATIONS
    // ════════════════════════════════════════════════════════════════════════
    Route::get('/formations', [FormationController::class, 'index']);
    Route::get('/formations/{formation}', [FormationController::class, 'show']);
    Route::post('/formations/{formation}/purchase', [FormationController::class, 'purchase']);

    // ════════════════════════════════════════════════════════════════════════
    // PRESTATAIRE
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('prestataire')->name('prestataire.')->group(function (): void {
        Route::get('/dashboard',     [PrestataireController::class, 'dashboard']);
        Route::get('/services',      [PrestataireController::class, 'services']);
        Route::get('/interventions', [PrestataireController::class, 'interventions']);
        Route::get('/agenda',        [PrestataireController::class, 'agenda']);
        Route::get('/finances',      [PrestataireController::class, 'finances']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // ADMIN
    // ════════════════════════════════════════════════════════════════════════
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/dashboard',               [AdminController::class, 'dashboard']);
        Route::get('/users',                   [AdminController::class, 'users']);
        Route::put('/users/{user}',            [AdminController::class, 'updateUserStatus']);
        Route::get('/properties',              [AdminController::class, 'properties']);
        Route::put('/properties/{property}',   [AdminController::class, 'updatePropertyStatus']);
        Route::get('/finances',                [AdminController::class, 'finances']);
        Route::get('/services',                [AdminController::class, 'services']);

        // ── Supervision du processus locatif ──────────────────────────────
        Route::get('/rental-stats',                              [AdminController::class, 'rentalStats']);
        Route::get('/rental-procedures',                         [AdminController::class, 'rentalProcedures']);
        Route::get('/rental-procedures/{id}',                    [AdminController::class, 'rentalProcedureDetail']);
        Route::post('/rental-procedures/{id}/status',            [AdminController::class, 'updateRentalStatus']);
        Route::get('/agents',                                    [AdminController::class, 'listAgents']);
        Route::post('/properties/{property}/assign-agent',       [AdminController::class, 'assignAgentToProperty']);
        Route::post('/rental-procedures/{visitId}/assign-agent', [AdminController::class, 'assignAgent']);

        // — Gestion des demandes de publication
        Route::get('/publication-requests',              [AdminController::class, 'listPublicationRequests']);
        Route::post('/publication-requests/{id}/assign', [AdminController::class, 'assignAgentToPublicationRequest']);
    });
});
