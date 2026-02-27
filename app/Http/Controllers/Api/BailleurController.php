<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Property;
use App\Models\Rental;
use Illuminate\Http\Request;

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

    /**
     * Liste des candidatures (rentals en attente)
     */
    public function applications(Request $request)
    {
        $user = $request->user();

        $applications = Rental::with(['property.primaryImage', 'tenant'])->get()
            ->filter(function ($r) use ($user) {
                return (int)$r->property->user_id === (int)$user->id && $r->status === 'pending';
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    /**
     * Mettre à jour le statut d'une candidature
     */
    public function updateApplicationStatus(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|in:active,rejected,cancelled',
        ]);

        $application = Rental::with('property')->findOrFail($id);

        if ((int) $application->property->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à gérer cette candidature.',
            ], 403);
        }

        $application->status = $request->status;

        if ($request->status === 'active') {
            $application->start_date = now();
        }

        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Statut de la candidature mis à jour.',
            'data' => $application->load('tenant'),
        ]);
    }

    /**
     * Liste des visites pour le bailleur
     */
    public function visits(Request $request)
    {
        $user = $request->user();

        $visits = \App\Models\Visit::with(['property.primaryImage', 'visitor'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visits,
        ]);
    }

    /**
     * Mettre à jour le statut d'une visite
     */
    public function updateVisitStatus(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|in:confirmed,cancelled,completed',
        ]);

        $visit = \App\Models\Visit::where('id', $id)
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $visit->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de la visite mis à jour.',
            'data' => $visit->load('user'),
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
}
