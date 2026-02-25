<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Http\Request;

class PrestataireController extends Controller
{
    /**
     * Get dashboard data for the prestataire
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Count interventions by status
        $stats = [
            'pending'   => Intervention::whereHas('service', fn($q) => $q->where('provider_id', $user->id))->where('status', 'pending')->count(),
            'active'    => Intervention::whereHas('service', fn($q) => $q->where('provider_id', $user->id))->where('status', 'accepted')->count(),
            'completed' => Intervention::whereHas('service', fn($q) => $q->where('provider_id', $user->id))->where('status', 'completed')->count(),
            'earnings'  => Transaction::where('user_id', $user->id)->where('type', 'credit')->sum('amount'),
        ];

        // Recent interventions
        $recentInterventions = Intervention::with(['service', 'requester', 'property'])
            ->whereHas('service', fn($q) => $q->where('provider_id', $user->id))
            ->latest()
            ->take(5)
            ->get();

        // Today's schedule
        $todaySchedule = Intervention::with(['service', 'requester', 'property'])
            ->whereHas('service', fn($q) => $q->where('provider_id', $user->id))
            ->whereDate('scheduled_at', now()->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_interventions' => $recentInterventions,
                'today_schedule' => $todaySchedule,
            ]
        ]);
    }

    /**
     * List of services offered by the provider
     */
    public function services(Request $request)
    {
        $services = Service::with('category')
            ->where('provider_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    /**
     * List of interventions assigned to the provider
     */
    public function interventions(Request $request)
    {
        $interventions = Intervention::with(['service', 'requester', 'property'])
            ->whereHas('service', fn($q) => $q->where('provider_id', $request->user()->id))
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $interventions
        ]);
    }

    /**
     * Upcoming interventions
     */
    public function agenda(Request $request)
    {
        $agenda = Intervention::with(['service', 'requester', 'property'])
            ->whereHas('service', fn($q) => $q->where('provider_id', $request->user()->id))
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agenda
        ]);
    }

    /**
     * Earnings and transactions
     */
    public function finances(Request $request)
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}
