<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Visit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    /**
     * Dashboard statistics for the agent
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Stats
        $managedPropertiesCount = Property::where('user_id', $user->id)->count();

        $activeRentalsCount = Rental::whereHas('property', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('status', 'active')->count();

        $pendingVisitsCount = Visit::whereHas('property', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('status', 'pending')->count();

        // Recent Missions (simplified as visits and rental applications)
        $missions = Visit::with(['property', 'visitor'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // Agenda (today's visits)
        $agenda = Visit::with(['property', 'visitor'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->whereDate('scheduled_at', now()->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'managed_properties' => $managedPropertiesCount,
                    'active_rentals' => $activeRentalsCount,
                    'pending_visits' => $pendingVisitsCount,
                    'rating' => 4.9, // Static for now
                    'is_certified' => $user->formations()->where('user_formations.status', 'completed')->exists(),
                ],
                'missions' => $missions,
                'agenda' => $agenda,
            ]
        ]);
    }

    /**
     * List of properties managed by the agent
     */
    public function properties(Request $request)
    {
        $user = $request->user();
        $properties = Property::where('user_id', $user->id)
            ->with(['primaryImage', 'rentals'])
            ->withCount('visits')
            ->latest()
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $properties
        ]);
    }

    /**
     * List of clients (tenants) managed by the agent
     */
    public function clients(Request $request)
    {
        $user = $request->user();

        // Clients are users who have active rentals on properties managed by this agent
        $clients = User::whereHas('rentals', function ($query) use ($user) {
            $query->whereHas('property', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'active');
        })->get();

        return response()->json([
            'success' => true,
            'data' => $clients
        ]);
    }

    /**
     * List of missions (visits and pending actions)
     */
    public function missions(Request $request)
    {
        $user = $request->user();
        $missions = Visit::with(['property', 'visitor'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $missions
        ]);
    }

    /**
     * Agent's agenda (scheduled visits)
     */
    public function agenda(Request $request)
    {
        $user = $request->user();
        $agenda = Visit::with(['property', 'visitor'])
            ->whereHas('property', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'confirmed')
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agenda
        ]);
    }
}
