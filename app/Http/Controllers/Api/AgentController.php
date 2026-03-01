<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AgentController — Gestion complète du processus locatif par l'agent HMC.
 *
 * Flux : Visite → Dossier → Paiement → Confirmation → Statut Locataire accordé
 */
class AgentController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════════════════════════════════

    public function dashboard(Request $request): JsonResponse
    {
        /** @var \App\Models\User $agent */
        $agent = $request->user();

        $stats = [
            'managed_properties'      => Property::where('agent_id', $agent->id)->count(),
            'pending_visits'          => Visit::where('agent_id', $agent->id)->where('status', 'pending')->count(),
            'pending_applications'    => RentalApplication::where('agent_id', $agent->id)->where('status', 'pending')->count(),
            'pending_payment_confirm' => Rental::where('agent_id', $agent->id)->where('status', 'pending')->count(),
            'active_rentals'          => Rental::where('agent_id', $agent->id)->where('status', 'active')->count(),
            'rating'                  => 4.9,
            'is_certified'            => $agent->formations()->where('user_formations.status', 'completed')->exists(),
        ];

        // Visites du jour
        $agenda = Visit::with(['property', 'visitor'])
            ->where('agent_id', $agent->id)
            ->whereDate('scheduled_at', now()->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        // Dernières visites
        $recentVisits = Visit::with(['property', 'visitor'])
            ->where('agent_id', $agent->id)
            ->latest()
            ->take(5)
            ->get();

        // Dossiers récents
        $recentApplications = RentalApplication::with(['property', 'applicant'])
            ->where('agent_id', $agent->id)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => compact('stats', 'agenda', 'recentVisits', 'recentApplications'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BIENS
    // ══════════════════════════════════════════════════════════════════════════

    public function properties(Request $request): JsonResponse
    {
        $agent = $request->user();

        $properties = Property::with(['primaryImage', 'owner'])
            ->where('agent_id', $agent->id)
            ->withCount('visits')
            ->latest()
            ->paginate(12);

        return response()->json(['success' => true, 'data' => $properties]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CLIENTS
    // ══════════════════════════════════════════════════════════════════════════

    public function clients(Request $request): JsonResponse
    {
        $agent = $request->user();

        $clients = User::whereHas('rentals', function ($query) use ($agent): void {
            $query->where('agent_id', $agent->id)->where('status', 'active');
        })->get();

        return response()->json(['success' => true, 'data' => $clients]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 1 : VISITES
    // ══════════════════════════════════════════════════════════════════════════

    public function visits(Request $request): JsonResponse
    {
        $agent = $request->user();

        $query = Visit::with(['property', 'visitor'])
            ->where('agent_id', $agent->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $visits = $query->latest('scheduled_at')->paginate(15);

        return response()->json(['success' => true, 'data' => $visits]);
    }

    /**
     * Agenda de l'agent (visites confirmées à venir)
     */
    public function agenda(Request $request): JsonResponse
    {
        $agent = $request->user();

        $agenda = Visit::with(['property', 'visitor'])
            ->where('agent_id', $agent->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['success' => true, 'data' => $agenda]);
    }

    /**
     * Missions de l'agent (toutes les actions en attente)
     */
    public function missions(Request $request): JsonResponse
    {
        $agent = $request->user();

        $pendingVisits        = Visit::with(['property', 'visitor'])->where('agent_id', $agent->id)->where('status', 'pending')->latest()->get();
        $pendingApplications  = RentalApplication::with(['property', 'applicant'])->where('agent_id', $agent->id)->where('status', 'pending')->latest()->get();
        $pendingPayments      = Rental::with(['property', 'tenant'])->where('agent_id', $agent->id)->where('status', 'pending')->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'pending_visits'       => $pendingVisits,
                'pending_applications' => $pendingApplications,
                'pending_payments'     => $pendingPayments,
            ],
        ]);
    }

    /**
     * L'agent confirme la visite (de son côté).
     * Si l'utilisateur a aussi confirmé → visite "completed" → dossier débloqué.
     */
    public function confirmVisit(Request $request, int $visitId): JsonResponse
    {
        /** @var \App\Models\User $agent */
        $agent = $request->user();

        // Trouver la visite : soit l'agent est assigné, soit la visite n'a pas encore d'agent
        $visit = Visit::where('id', $visitId)
            ->where(function ($q) use ($agent) {
                $q->where('agent_id', $agent->id)
                    ->orWhereNull('agent_id'); // visite sans agent assigné → n'importe quel agent HMC peut prendre
            })
            ->firstOrFail();

        // Si pas d'agent assigné, s'auto-assigner

        if (is_null($visit->agent_id)) {
            $visit->update(['agent_id' => $agent->id]);
        }

        if ($visit->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Cette visite a été annulée.'], 422);
        }

        $visit->update([
            'confirmed_by_agent' => true,
            'visited_at'         => now(),
            'status'             => 'confirmed',
        ]);

        $visit->checkAndComplete();
        $visit->refresh();

        return response()->json([
            'success'        => true,
            'message'        => 'Visite confirmée par l\'agent.',
            'data'           => $visit->load(['property', 'visitor']),
            'both_confirmed' => $visit->isBothConfirmed(),
            'can_apply'      => $visit->status === 'completed',
        ]);
    }

    /**
     * L'agent annule une visite.
     */
    public function cancelVisit(Request $request, int $visitId): JsonResponse
    {
        $agent = $request->user();
        $visit = Visit::where('id', $visitId)
            ->where(function ($q) use ($agent) {
                $q->where('agent_id', $agent->id)->orWhereNull('agent_id');
            })
            ->firstOrFail();
        $visit->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Visite annulée.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 2 : DOSSIERS DE CANDIDATURE
    // ══════════════════════════════════════════════════════════════════════════

    public function applications(Request $request): JsonResponse
    {
        $agent = $request->user();

        $query = RentalApplication::with(['property', 'applicant', 'visit'])
            ->where('agent_id', $agent->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json(['success' => true, 'data' => $query->latest()->paginate(15)]);
    }

    public function showApplication(int $id): JsonResponse
    {
        $agent = Auth::user();
        $application = RentalApplication::with(['property.owner', 'applicant', 'visit', 'agent'])
            ->where('agent_id', $agent->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * L'agent valide le dossier → crée le Rental en attente de paiement.
     */
    public function validateApplication(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'advance_months' => 'required|integer|min:1|max:12',
            'start_date'     => 'required|date|after:today',
            'notes'          => 'nullable|string',
        ]);

        /** @var \App\Models\User $agent */
        $agent = Auth::user();

        $application = RentalApplication::with('property')
            ->where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        DB::transaction(function () use ($application, $request, $agent): void {
            $application->update([
                'status'      => 'validated',
                'reviewed_at' => now(),
                'reviewed_by' => $agent->id,
            ]);

            $property     = $application->property;
            $advanceMonths = (int) $request->advance_months;
            $price        = (float) $property->price;

            Rental::create([
                'property_id'          => $application->property_id,
                'application_id'       => $application->id,
                'tenant_id'            => $application->user_id,
                'agent_id'             => $agent->id,
                'price'                => $price,
                'advance_months'       => $advanceMonths,
                'caution_amount'       => $price * 2,
                'advance_amount'       => $price * $advanceMonths,
                'start_date'           => $request->start_date,
                'status'               => 'pending',
                'payment_phase_status' => 'pending',
                'payment_status'       => 'unpaid',
                'notes'                => $request->notes,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Dossier validé. Le locataire peut maintenant procéder au paiement.',
            'data'    => $application->fresh()->load('rental'),
        ]);
    }

    /**
     * L'agent rejette un dossier.
     */
    public function rejectApplication(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $agent = Auth::user();

        $application = RentalApplication::where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $application->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'reviewed_at'      => now(),
            'reviewed_by'      => $agent->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Dossier rejeté.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 3 : CONFIRMATION DU PAIEMENT INITIAL
    // ══════════════════════════════════════════════════════════════════════════

    public function rentals(Request $request): JsonResponse
    {
        $agent = $request->user();

        $query = Rental::with(['property', 'tenant', 'application'])
            ->where('agent_id', $agent->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json(['success' => true, 'data' => $query->latest()->paginate(15)]);
    }

    /**
     * L'agent confirme la réception du paiement initial → activation de la location.
     * C'est ici que le rôle "locataire" est attribué.
     */
    public function confirmPayment(Request $request, int $rentalId): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|in:momo,om,card,cash',
            'notes'          => 'nullable|string',
        ]);

        /** @var \App\Models\User $agent */
        $agent = Auth::user();

        $rental = Rental::with(['property', 'tenant'])
            ->where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->findOrFail($rentalId);

        DB::transaction(function () use ($rental, $agent): void {
            // 1. Activer la location
            $rental->update([
                'status'               => 'active',
                'payment_phase_status' => 'confirmed',
                'payment_confirmed_at' => now(),
                'payment_confirmed_by' => $agent->id,
                'payment_status'       => 'paid',
            ]);

            // 2. Marquer le bien comme loué
            $rental->property->update(['status' => 'rented']);

            // 3. Attribuer le rôle "locataire"
            $tenant = $rental->tenant;
            if ($tenant && ! $tenant->hasRole('locataire')) {
                $tenant->addRole('locataire');
            }
            // Définir locataire comme rôle actif
            if ($tenant && $tenant->role !== 'locataire') {
                $tenant->update(['role' => 'locataire']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Paiement confirmé. La location est active. Le rôle locataire a été attribué.',
            'data'    => $rental->fresh()->load(['property', 'tenant']),
        ]);
    }
}
