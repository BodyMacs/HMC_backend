<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentalApplication;
use App\Models\Visit;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * VisiteController — Actions du futur locataire sur les visites.
 */
class VisiteController extends Controller
{
    /**
     * Réserver une visite.
     * Accessible à tout utilisateur authentifié (pas besoin du rôle locataire).
     */
    public function book(Request $request): JsonResponse
    {
        $request->validate([
            'property_id'  => 'required|exists:properties,id',
            'scheduled_at' => 'required|date|after:now',
            'notes'        => 'nullable|string',
            'fee_payment_method' => 'nullable|in:momo,om,card,cash',
        ]);

        $user = Auth::user();

        // Vérifier qu'il n'y a pas déjà une visite en cours pour ce bien
        $existing = Visit::where('user_id', $user->id)
            ->where('property_id', $request->property_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une visite programmée ou en attente pour ce bien.',
                'data'    => $existing,
            ], 422);
        }

        $property = \App\Models\Property::findOrFail($request->property_id);

        $visit = Visit::create([
            'property_id'        => $request->property_id,
            'user_id'            => $user->id,
            'agent_id'           => $property->agent_id,
            'scheduled_at'       => $request->scheduled_at,
            'status'             => 'pending',
            'notes'              => $request->notes,
            'visit_fee'          => Visit::getVisitFee(),
            'fee_payment_status' => $request->fee_payment_method ? 'paid' : 'pending',
            'fee_payment_method' => $request->fee_payment_method,
        ]);

        // ── Notification client ──
        NotificationService::visitBooked(
            $user->id,
            $property->title,
            $visit->id
        );

        // ── Notification agent si assigné ──
        if ($property->agent_id) {
            $scheduledAt = \Carbon\Carbon::parse($request->scheduled_at)->format('d/m/Y à H\hi');
            NotificationService::visitAssignedToAgent(
                $property->agent_id,
                $property->title,
                $scheduledAt,
                $visit->id
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre visite a été réservée. Un agent vous contactera pour confirmer.',
            'data'    => $visit->load('property'),
        ], 201);
    }

    /**
     * L'utilisateur confirme que la visite a bien eu lieu (de son côté).
     * → Si l'agent a aussi confirmé, la visite passe à "completed".
     */
    public function userConfirm(Request $request, int $visitId): JsonResponse
    {
        $user = Auth::user();

        $visit = Visit::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($visitId);

        $visit->update(['confirmed_by_user' => true]);

        // Si l'agent a déjà confirmé → visite terminée
        $visit->checkAndComplete();
        $visit->refresh();

        return response()->json([
            'success'        => true,
            'message'        => 'Visite confirmée de votre côté.',
            'data'           => $visit->load('property'),
            'both_confirmed' => $visit->isBothConfirmed(),
            'can_apply'      => $visit->status === 'completed',
        ]);
    }

    /**
     * Liste des visites de l'utilisateur connecté.
     */
    public function myVisits(): JsonResponse
    {
        $user = Auth::user();

        $visits = Visit::with(['property.primaryImage', 'agent'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $visits]);
    }

    /**
     * Annuler une visite (par l'utilisateur).
     */
    public function cancel(int $visitId): JsonResponse
    {
        $user = Auth::user();

        $visit = Visit::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($visitId);

        $propertyTitle = $visit->property->title ?? 'ce bien';
        $visit->update(['status' => 'cancelled']);

        // ── Notification ──
        NotificationService::visitCancelled($user->id, $propertyTitle, $visit->id);

        return response()->json(['success' => true, 'message' => 'Visite annulée.']);
    }
}
