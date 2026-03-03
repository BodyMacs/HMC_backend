<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Property;
use App\Models\PropertyRequest;
use App\Models\Rental;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Global dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_users'          => User::count(),
            'active_users'         => User::where('status', 'active')->count(),
            'total_properties'     => Property::count(),
            'pending_properties'   => Property::where('status', 'pending')->count(),
            'total_transactions'   => Transaction::count(),
            'total_revenue'        => Transaction::where('status', 'successful')->sum('amount'),
            'monthly_revenue'      => Transaction::where('status', 'successful')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
            // Stats location
            'pending_rentals'      => Rental::where('status', 'pending')->count(),
            'active_rentals'       => Rental::where('status', 'active')->count(),
            'total_rentals'        => Rental::count(),
        ];

        $recentUsers        = User::latest()->take(5)->get();
        $recentProperties   = Property::with('owner')->latest()->take(5)->get();
        $recentRentals      = Rental::with(['tenant:id,name,email,phone', 'property:id,title,city,price'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats'             => $stats,
                'recent_users'      => $recentUsers,
                'recent_properties' => $recentProperties,
                'recent_rentals'    => $recentRentals,
            ],
        ]);
    }

    /**
     * List all users
     */
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request): void {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Update user status (active/inactive)
     */
    public function updateUserStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|string|in:active,inactive,pending',
        ]);

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de l\'utilisateur mis à jour.',
        ]);
    }

    /**
     * List all properties
     */
    public function properties(Request $request)
    {
        $query = Property::with(['owner', 'images', 'agent:id,name,email,phone']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $properties = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $properties,
        ]);
    }

    /**
     * Approve or reject a property
     */
    public function updatePropertyStatus(Request $request, Property $property)
    {
        $request->validate([
            'status' => 'required|string|in:active,rejected,rented,sold,pending',
        ]);

        $property->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut du bien mis à jour.',
        ]);
    }

    /**
     * Financial reports
     */
    public function finances()
    {
        $transactions = Transaction::with('user')->latest()->paginate(20);
        $totalRevenue = Transaction::where('status', 'completed')->sum('amount');

        // Monthly breakdown
        $monthlyRevenue = Transaction::select(
            DB::raw('sum(amount) as total'),
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month")
        )
            ->where('status', 'completed')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(12)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'total_revenue' => $totalRevenue,
                'monthly_breakdown' => $monthlyRevenue,
            ],
        ]);
    }

    /**
     * List all services and categories
     */
    public function services()
    {
        $services = Service::with(['category', 'provider'])->latest()->paginate(20);
        $interventions = Intervention::with(['service', 'requester'])->latest()->take(10)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $services,
                'recent_interventions' => $interventions,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // SUPERVISION DU PROCESSUS LOCATIF (basé sur le modèle Rental)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * KPIs de supervision des locations pour l'admin
     * GET /api/admin/rental-stats
     */
    public function rentalStats()
    {
        $stats = [
            'total'     => Rental::count(),
            'pending'   => Rental::where('status', 'pending')->count(),
            'active'    => Rental::where('status', 'active')->count(),
            'finished'  => Rental::where('status', 'finished')->count(),
            'cancelled' => Rental::where('status', 'cancelled')->count(),
            // Revenue locatif total (somme des loyers actifs)
            'monthly_rental_revenue' => Rental::where('status', 'active')->sum('price'),
        ];

        // Évolution mensuelle (6 derniers mois)
        $monthlyTrend = Rental::select(
            DB::raw("strftime('%Y-%m', created_at) as month"),
            DB::raw('count(*) as total'),
            DB::raw("sum(case when status='active' then 1 else 0 end) as active")
        )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats'         => $stats,
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }

    /**
     * Liste paginée de toutes les demandes de location (admin view)
     * GET /api/admin/rental-procedures
     */
    public function rentalProcedures(Request $request)
    {
        $query = Rental::with([
            'tenant:id,name,email,phone',
            'property:id,title,city,price,type,user_id',
            'property.owner:id,name,phone',
        ]);

        // Filtre statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtre recherche (nom locataire, titre bien, ville)
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', fn($t) => $t->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search))
                    ->orWhereHas('property', fn($p) => $p->where('title', 'like', $search)
                        ->orWhere('city', 'like', $search));
            });
        }

        // Filtre période
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $rentals = $query->latest()->paginate(20);

        // Enrichir chaque location avec un % de progression
        $rentals->getCollection()->transform(function ($rental) {
            $rental->progress_percent = match ($rental->status) {
                'pending'   => 25,
                'active'    => 100,
                'finished'  => 100,
                'cancelled' => 0,
                default     => 10,
            };
            $rental->status_label = match ($rental->status) {
                'pending'   => 'En attente',
                'active'    => 'Location active',
                'finished'  => 'Terminée',
                'cancelled' => 'Annulée',
                default     => $rental->status,
            };
            return $rental;
        });

        return response()->json(['success' => true, 'data' => $rentals]);
    }

    /**
     * Détail complet d'une demande de location
     * GET /api/admin/rental-procedures/{id}
     */
    public function rentalProcedureDetail(int $rentalId)
    {
        $rental = Rental::with([
            'tenant:id,name,email,phone,avatar,city,role',
            'property:id,title,city,price,type,location,status,user_id,description',
            'property.owner:id,name,email,phone',
        ])->findOrFail($rentalId);

        return response()->json([
            'success' => true,
            'data'    => $rental,
        ]);
    }

    /**
     * Mettre à jour le statut d'une location (action admin)
     * POST /api/admin/rental-procedures/{id}/status
     */
    public function updateRentalStatus(Request $request, int $rentalId)
    {
        $request->validate([
            'status' => 'required|in:pending,active,finished,cancelled',
            'reason' => 'nullable|string|max:500',
        ]);

        $rental = Rental::with(['tenant', 'property'])->findOrFail($rentalId);
        $oldStatus = $rental->status;
        $rental->status = $request->status;

        if ($request->filled('reason')) {
            $rental->notes = ($rental->notes ? $rental->notes . "\n" : '') .
                '[Admin] ' . now()->format('d/m/Y') . ': ' . $request->reason;
        }

        $rental->save();

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour : {$oldStatus} → {$request->status}",
            'data'    => $rental->fresh(['tenant', 'property']),
        ]);
    }

    /**
     * Assigner un agent à une visite (gardé pour compatibilité)
     * POST /api/admin/rental-procedures/{visitId}/assign-agent
     */
    public function assignAgent(Request $request, int $visitId)
    {
        $request->validate([
            'agent_id' => 'required|integer|exists:users,id',
        ]);

        $visit = Visit::findOrFail($visitId);
        $agent = User::where('id', $request->agent_id)
            ->where('role', 'agent')
            ->firstOr(fn() => null);

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur n\'est pas un agent.',
            ], 422);
        }

        $visit->update(['agent_id' => $request->agent_id]);

        return response()->json([
            'success' => true,
            'message' => 'Agent assigné avec succès.',
            'data'    => [
                'visit_id'   => $visit->id,
                'agent_name' => $agent->name,
            ],
        ]);
    }

    /**
     * List all users with 'agent' role
     */
    public function listAgents()
    {
        $agents = User::where('role', 'agent')
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'phone')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $agents,
        ]);
    }

    /**
     * Assign an agent to a property
     */
    public function assignAgentToProperty(Request $request, Property $property)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $agent = User::where('id', $validated['agent_id'])
            ->where('role', 'agent')
            ->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'L\'utilisateur sélectionné n\'est pas un agent.',
            ], 422);
        }

        $property->update(['agent_id' => $agent->id]);

        return response()->json([
            'success' => true,
            'message' => 'Agent assigné à la propriété avec succès.',
            'data'    => $property->load('agent:id,name,email,phone'),
        ]);
    }

    /**
     * Liste des demandes de publication des bailleurs
     * GET /api/admin/publication-requests
     */
    public function listPublicationRequests(Request $request)
    {
        $query = PropertyRequest::with(['bailleur:id,name,email,phone', 'agent:id,name,phone']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $requests,
        ]);
    }

    /**
     * Assigner un agent à une demande de publication
     * POST /api/admin/publication-requests/{id}/assign
     */
    public function assignAgentToPublicationRequest(Request $request, int $id)
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $propRequest = PropertyRequest::findOrFail($id);

        $agent = User::where('id', $request->agent_id)
            ->where('role', 'agent')
            ->first();

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'L\'utilisateur n\'est pas un agent.'], 422);
        }

        $propRequest->update([
            'agent_id' => $agent->id,
            'status'   => 'assigned',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agent assigné à la demande de publication.',
            'data'    => $propRequest->load(['bailleur', 'agent']),
        ]);
    }
}
