<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Property;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
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
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_properties' => Property::count(),
            'pending_properties' => Property::where('status', 'pending')->count(),
            'total_transactions' => Transaction::count(),
            'total_revenue' => Transaction::where('status', 'completed')->sum('amount'),
            'monthly_revenue' => Transaction::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
        ];

        $recentUsers = User::latest()->take(5)->get();
        $recentProperties = Property::with('user')->latest()->take(5)->get();
        $recentTransactions = Transaction::with('user')->latest()->take(5)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_users' => $recentUsers,
                'recent_properties' => $recentProperties,
                'recent_transactions' => $recentTransactions,
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
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
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
        $query = Property::with(['user', 'images']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $properties = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $properties,
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
}
