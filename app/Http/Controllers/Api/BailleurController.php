<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Property;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\Visit;
use App\Models\PropertyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BailleurController extends Controller
{
    /**
     * Dashboard stats du bailleur connecté
     * GET /api/bailleur/dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // ── Biens du bailleur ───────────────────────────────────────────
        $properties = Property::where('user_id', $user->id)
            ->with(['primaryImage', 'rentals' => fn($q) => $q->where('status', 'active')])
            ->get();

        $totalProperties = $properties->count();

        // Biens occupés (location active)
        $occupiedCount = $properties->filter(
            fn($p) => $p->rentals->isNotEmpty()
        )->count();

        $occupancyRate = $totalProperties > 0
            ? round(($occupiedCount / $totalProperties) * 100)
            : 0;

        // ── Revenus du mois en cours ────────────────────────────────────
        $monthlyRevenue = Rental::whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->sum('monthly_rent');

        // ── Loyers impayés (location active + paiement en retard) ──────
        $unpaidRentals = Rental::whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->where('payment_status', 'unpaid')
            ->get();

        $unpaidCount = $unpaidRentals->count();
        $unpaidAmount = $unpaidRentals->sum('monthly_rent');

        // ── Interventions en attente ────────────────────────────────────
        $interventionCount = Intervention::whereHas(
            'property',
            fn($q) => $q->where('user_id', $user->id)
        )->whereIn('status', ['pending', 'in_progress'])->count();

        // ── Top 5 biens pour la liste du dashboard ─────────────────────
        $featuredProperties = $properties->map(function ($p) {
            $pi = $p->primaryImage;
            $path = $pi ? $pi->path : null;
            $img = $path
                ? (str_starts_with($path, 'http') ? $path : asset('storage/' . $path))
                : 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=300';

            $activeRental = $p->rentals->first();

            return [
                'id' => $p->id,
                'title' => $p->title,
                'location' => $p->location,
                'city' => $p->city,
                'category' => $p->category,
                'price' => (float) $p->price,
                'image' => $img,
                'status' => $activeRental ? 'occupied' : 'available',
                'tenant' => $activeRental?->tenant?->name,
                'payment_status' => $activeRental?->payment_status ?? null,
            ];
        })->sortByDesc(fn($p) => $p['status'] === 'occupied')->values()->take(6);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'monthly_revenue' => (float) $monthlyRevenue,
                    'total_properties' => $totalProperties,
                    'occupied_count' => $occupiedCount,
                    'available_count' => $totalProperties - $occupiedCount,
                    'occupancy_rate' => $occupancyRate,
                    'unpaid_count' => $unpaidCount,
                    'unpaid_amount' => (float) $unpaidAmount,
                    'interventions' => $interventionCount,
                ],
                'properties' => $featuredProperties,
            ],
        ]);
    }

    /**
     * Liste complète des biens du bailleur (avec pagination)
     * GET /api/bailleur/properties
     */
    public function properties(Request $request)
    {
        $user = $request->user();

        $query = Property::where('user_id', $user->id)
            ->with(['primaryImage', 'rentals' => fn($q) => $q->where('status', 'active')->with('tenant:id,name,phone')])
            ->withCount('visits');

        // Filtres optionnels
        if ($request->filled('status')) {
            if ($request->status === 'occupied') {
                $query->whereHas('rentals', fn($q) => $q->where('status', 'active'));
            } elseif ($request->status === 'available') {
                $query->whereDoesntHave('rentals', fn($q) => $q->where('status', 'active'));
            }
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $properties = $query->latest()->paginate(12);

        $properties->getCollection()->transform(function ($p) {
            $pi = $p->primaryImage;
            $path = $pi ? $pi->path : null;
            $p->image = $path
                ? (str_starts_with($path, 'http') ? $path : asset('storage/' . $path))
                : 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=400';

            $activeRental = $p->rentals->first();
            $p->rental_status = $activeRental ? 'occupied' : 'available';
            $p->tenant = $activeRental?->tenant;
            $p->monthly_rent = $activeRental?->monthly_rent ?? $p->price;
            $p->payment_status = $activeRental?->payment_status;

            return $p;
        });

        return response()->json([
            'success' => true,
            'data' => $properties,
        ]);
    }

    /**
     * Profil du bailleur connecté
     * GET /api/bailleur/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user()->loadCount('properties');

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Mise à jour du profil
     * PUT /api/bailleur/profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:100',
            'bio' => 'sometimes|string|max:500',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => $user->fresh(),
            'message' => 'Profil mis à jour avec succès.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SUIVI DU PROCESSUS LOCATIF (lecture seule)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Statut du processus locatif pour un bien donné — LECTURE SEULE.
     * Le bailleur observe l'avancement sans pouvoir intervenir.
     * GET /api/bailleur/properties/{id}/rental-status
     */
    public function propertyRentalStatus(Request $request, int $propertyId)
    {
        $user = $request->user();

        // Vérifier que ce bien appartient bien au bailleur
        $property = Property::where('id', $propertyId)
            ->where('user_id', $user->id)
            ->with(['agent:id,name,phone'])
            ->firstOrFail();

        // Phase 1 : Visite la plus récente
        $latestVisit = Visit::where('property_id', $propertyId)
            ->with('visitor:id,name')
            ->latest()
            ->first();

        // Phase 2 : Dossier de candidature le plus récent
        $latestApplication = RentalApplication::where('property_id', $propertyId)
            ->latest()
            ->first();

        // Phase 3 : Location (contrat final)
        $activeRental = Rental::where('property_id', $propertyId)
            ->whereIn('status', ['pending', 'active'])
            ->with('tenant:id,name,phone')
            ->first();

        // Calculer la phase actuelle et le % d'avancement
        [$phase, $percent, $phaseLabel] = $this->computeRentalPhase(
            $latestVisit,
            $latestApplication,
            $activeRental
        );

        return response()->json([
            'success' => true,
            'data' => [
                'property' => [
                    'id'     => $property->id,
                    'title'  => $property->title,
                    'status' => $property->status,
                    'agent'  => $property->agent,
                ],
                'current_phase'   => $phase,
                'phase_label'     => $phaseLabel,
                'progress_percent' => $percent,
                // Phase 1 — info visite (sans identité du prospect)
                'visit' => $latestVisit ? [
                    'id'                 => $latestVisit->id,
                    'status'             => $latestVisit->status,
                    'scheduled_at'       => $latestVisit->scheduled_at,
                    'confirmed_by_agent' => $latestVisit->confirmed_by_agent,
                    'confirmed_by_user'  => $latestVisit->confirmed_by_user,
                ] : null,
                // Phase 2 — info dossier (sans données sensibles)
                'application' => $latestApplication ? [
                    'id'         => $latestApplication->id,
                    'status'     => $latestApplication->status,
                    'created_at' => $latestApplication->created_at,
                ] : null,
                // Phase 3 — info contrat (avec identité locataire une fois actif)
                'rental' => $activeRental ? [
                    'id'          => $activeRental->id,
                    'status'      => $activeRental->status,
                    'start_date'  => $activeRental->start_date,
                    'end_date'    => $activeRental->end_date,
                    'price'       => $activeRental->price,
                    'tenant'      => $activeRental->status === 'active' ? $activeRental->tenant : null,
                    'payment_status' => $activeRental->payment_status,
                ] : null,
            ],
        ]);
    }

    /**
     * Calcule la phase courante et le % d'avancement du processus locatif.
     */
    private function computeRentalPhase(?Visit $visit, ?RentalApplication $app, ?Rental $rental): array
    {
        if ($rental?->status === 'active') {
            return [3, 100, 'Location active'];
        }
        if ($rental?->status === 'pending') {
            return [3, 75, 'Paiement initial en attente'];
        }
        if ($app?->status === 'validated') {
            return [3, 60, 'Dossier validé — en attente de paiement'];
        }
        if (in_array($app?->status, ['pending', 'under_review'], true)) {
            return [2, 40, 'Dossier de candidature en cours'];
        }
        if ($visit?->status === 'completed') {
            return [2, 25, 'Visite confirmée — dossier à soumettre'];
        }
        if ($visit?->status === 'confirmed') {
            return [1, 15, 'Visite planifiée'];
        }
        if ($visit?->status === 'pending') {
            return [1, 10, 'Visite en attente de paiement'];
        }
        return [0, 0, 'Aucun processus en cours'];
    }

    /**
     * Liste des visites pour le bailleur — LECTURE SEULE (sans identité prospect).
     * GET /api/bailleur/visits
     */
    public function visits(Request $request)
    {
        $user = $request->user();

        $visits = Visit::with(['property:id,title,city', 'agent:id,name,phone'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->select([
                'id',
                'property_id',
                'agent_id',
                'scheduled_at',
                'visited_at',
                'status',
                'confirmed_by_user',
                'confirmed_by_agent',
                'visit_fee',
                'fee_payment_status',
                'created_at'
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $visits,
        ]);
    }

    /**
     * Liste des interventions pour le bailleur
     */
    public function interventions(Request $request)
    {
        $user = $request->user();

        $interventions = Intervention::with(['property.primaryImage', 'service', 'requester'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $interventions,
        ]);
    }

    /**
     * Mettre à jour le statut d'une intervention
     */
    public function updateInterventionStatus(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|in:in_progress,completed,cancelled',
        ]);

        $intervention = Intervention::where('id', $id)
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $intervention->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de l\'intervention mis à jour.',
            'data' => $intervention->load('service'),
        ]);
    }

    /**
     * Rapport financier du bailleur
     * GET /api/bailleur/finances
     */
    public function finances(Request $request)
    {
        $user = $request->user();

        // Stats financières de base
        $monthlyRevenue = Rental::whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->sum('monthly_rent');

        // Total collecté (simulé via transactions réussies de type payment)
        $totalCollected = \App\Models\Transaction::where('user_id', $user->id)
            ->where('type', 'payment')
            ->where('status', 'successful')
            ->sum('amount');

        // Recent Transactions
        $transactions = \App\Models\Transaction::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'monthly_revenue' => (float) $monthlyRevenue,
                    'total_collected' => (float) $totalCollected,
                    'balance' => (float) ($user->wallet?->balance ?? 0),
                ],
                'transactions' => $transactions,
            ],
        ]);
    }

    /**
     * Mes demandes de publication (audit terrain)
     * GET /api/bailleur/publication-requests
     */
    public function myPublicationRequests(Request $request)
    {
        $user = $request->user();
        $requests = PropertyRequest::with(['agent:id,name,phone'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $requests,
        ]);
    }

    /**
     * Soumettre une demande de publication (Phase 0 Audit)
     * POST /api/bailleur/publication-requests
     */
    public function submitPublicationRequest(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'type'           => 'required|in:rent,sale',
            'category'       => 'required|string',
            'price_estimate' => 'nullable|numeric',
            'city'           => 'required|string',
            'location'       => 'required|string',
            'description'    => 'nullable|string',
            'bedrooms'       => 'nullable|integer',
            'bathrooms'      => 'nullable|integer',
            'area'           => 'nullable|numeric',
            'documents'      => 'required|array',
            'documents.*'    => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $user = $request->user();

        // Si c'est un locataire, on lui attribue le rôle bailleur
        if (!$user->hasRole('bailleur')) {
            $user->addRole('bailleur');
        }

        $paths = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $paths[] = $file->store('audit_documents', 'public');
            }
        }

        $propertyRequest = PropertyRequest::create([
            'user_id'        => $user->id,
            'title'          => $validated['title'],
            'type'           => $validated['type'],
            'category'       => $validated['category'],
            'price_estimate' => $validated['price_estimate'],
            'city'           => $validated['city'],
            'location'       => $validated['location'],
            'description'    => $validated['description'],
            'bedrooms'       => $validated['bedrooms'],
            'bathrooms'      => $validated['bathrooms'],
            'area'           => $validated['area'],
            'documents'      => $paths,
            'status'         => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de publication soumise avec succès. Un administrateur va assigner un agent pour l\'audit.',
            'data'    => $propertyRequest,
        ], 201);
    }
}
