<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ProspectController — Actions du prospect tout au long du processus locatif.
 *
 * Flux :
 *   Phase 1 : Réserver une visite → Payer les frais → Confirmer après la visite
 *   Phase 2 : Soumettre un dossier → Uploader les documents → Signer le contrat
 *   Phase 3 : Attendre la confirmation du paiement par l'agent
 */
class ProspectController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 1 : VISITES
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Lister les visites du prospect connecté.
     * GET /api/prospect/visits
     */
    public function myVisits(Request $request): JsonResponse
    {
        $user = $request->user();

        $visits = Visit::with(['property:id,title,city,price,location', 'property.primaryImage', 'agent:id,name,phone'])
            ->where('user_id', $user->id)
            ->latest('scheduled_at')
            ->get()
            ->map(fn($v) => $this->enrichVisit($v));

        return response()->json(['success' => true, 'data' => $visits]);
    }

    /**
     * Réserver une visite pour un bien.
     * POST /api/prospect/visits
     */
    public function bookVisit(Request $request): JsonResponse
    {
        $request->validate([
            'property_id'  => 'required|integer|exists:properties,id',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $user     = $request->user();
        $property = Property::where('id', $request->property_id)
            ->where('status', 'active')
            ->with('agent:id,name,phone')
            ->firstOrFail();

        // Vérifier qu'il n'a pas déjà une visite en cours pour ce bien
        $existing = Visit::where('property_id', $request->property_id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une visite planifiée pour ce bien.',
                'data'    => $existing,
            ], 422);
        }

        $visit = Visit::create([
            'property_id'  => $request->property_id,
            'user_id'      => $user->id,
            'agent_id'     => $property->agent_id,
            'scheduled_at' => $request->scheduled_at,
            'status'       => 'pending',
            'visit_fee'    => Visit::getVisitFee(),
            'fee_payment_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visite réservée. Veuillez régler les frais de visite pour la confirmer.',
            'data'    => $visit->load(['property:id,title,city', 'agent:id,name,phone']),
        ], 201);
    }

    /**
     * Confirmer la visite du côté du prospect (après que la visite a eu lieu).
     * POST /api/prospect/visits/{id}/confirm
     */
    public function confirmVisit(Request $request, int $visitId): JsonResponse
    {
        $user  = $request->user();
        $visit = Visit::where('id', $visitId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->firstOrFail();

        if ($visit->confirmed_by_user) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà confirmé cette visite.',
            ], 422);
        }

        $visit->update(['confirmed_by_user' => true]);

        // Si les deux parties ont confirmé → visite terminée → phase 2 débloquée
        $visit->checkAndComplete();
        $visit->refresh();

        $canApply = $visit->status === 'completed';

        return response()->json([
            'success'       => true,
            'message'       => $canApply
                ? 'Visite confirmée par les deux parties ! Vous pouvez maintenant soumettre votre dossier.'
                : 'Votre confirmation a été enregistrée. En attente de la confirmation de l\'agent.',
            'data'          => $visit,
            'both_confirmed' => $canApply,
            'can_apply'      => $canApply,
        ]);
    }

    /**
     * Annuler une visite (avant qu'elle ait lieu).
     * POST /api/prospect/visits/{id}/cancel
     */
    public function cancelVisit(Request $request, int $visitId): JsonResponse
    {
        $user  = $request->user();
        $visit = Visit::where('id', $visitId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $visit->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Visite annulée.']);
    }

    /**
     * Payer les frais de visite via NotchPay.
     * POST /api/prospect/visits/{id}/pay
     */
    public function payVisitFee(Request $request, int $visitId): JsonResponse
    {
        $user  = $request->user();
        $visit = Visit::where('id', $visitId)
            ->where('user_id', $user->id)
            ->where('fee_payment_status', 'pending')
            ->firstOrFail();

        $notchPayService = app(\App\Services\NotchPayService::class);
        $reference = 'VISIT_' . $visit->id . '_' . time();

        \App\Models\Transaction::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'type' => 'payment',
            'amount' => $visit->visit_fee,
            'currency' => 'XAF',
            'status' => 'pending',
            'payment_method' => 'momo',
            'metadata' => [
                'action' => 'visit_fee',
                'visit_id' => $visit->id,
            ],
        ]);

        try {
            $paymentToken = $notchPayService->initializePayment([
                'amount' => $visit->visit_fee,
                'email' => $user->email,
                'reference' => $reference,
                'description' => "Frais de visite pour le bien: {$visit->property->title}",
                'metadata' => [
                    'action' => 'visit_fee',
                    'visit_id' => $visit->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => $paymentToken->authorization_url,
                'reference' => $reference,
                'message' => 'Paiement initialisé.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 2 : DOSSIER DE CANDIDATURE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Créer un dossier de candidature (après visite confirmée).
     * POST /api/prospect/applications
     */
    public function createApplication(Request $request): JsonResponse
    {
        $request->validate([
            'visit_id'                  => 'required|integer|exists:visits,id',
            'situation_professionnelle' => 'nullable|in:cdi,cdd,independant,etudiant,retraite,sans_emploi',
            'revenus_mensuels'          => 'nullable|numeric|min:0',
            'has_garant'                => 'boolean',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        $user  = $request->user();
        $visit = Visit::where('id', $request->visit_id)
            ->where('user_id', $user->id)
            ->where('status', 'completed') // La visite DOIT être terminée
            ->firstOrFail();

        // Vérifier qu'il n'a pas déjà un dossier pour cette visite
        $existing = RentalApplication::where('visit_id', $visit->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Un dossier existe déjà pour cette visite.',
                'data'    => $existing,
            ], 422);
        }

        $application = RentalApplication::create([
            'property_id'               => $visit->property_id,
            'user_id'                   => $user->id,
            'visit_id'                  => $visit->id,
            'agent_id'                  => $visit->agent_id,
            'situation_professionnelle' => $request->situation_professionnelle,
            'revenus_mensuels'          => $request->revenus_mensuels,
            'has_garant'                => $request->boolean('has_garant', false),
            'notes'                     => $request->notes,
            'status'                    => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dossier créé. Veuillez maintenant uploader vos documents et signer le contrat.',
            'data'    => $application->load(['property:id,title,city', 'agent:id,name']),
        ], 201);
    }

    /**
     * Voir le détail de son dossier de candidature.
     * GET /api/prospect/applications/{id}
     */
    public function showApplication(int $applicationId): JsonResponse
    {
        $user        = request()->user();
        $application = RentalApplication::with([
            'property:id,title,city,price,location',
            'property.primaryImage',
            'agent:id,name,phone',
            'visit',
            'rental',
        ])->where('id', $applicationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Enrichir avec la phase actuelle
        $application->phase_label = match ($application->status) {
            'pending'      => 'Dossier en attente de révision',
            'under_review' => 'Dossier en cours d\'examen par l\'agent',
            'validated'    => 'Dossier validé — procédez au paiement',
            'rejected'     => 'Dossier rejeté',
            default        => $application->status,
        };

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * Lister tous les dossiers du prospect.
     * GET /api/prospect/applications
     */
    public function myApplications(Request $request): JsonResponse
    {
        $user = $request->user();

        $applications = RentalApplication::with([
            'property:id,title,city,price',
            'property.primaryImage',
            'rental:id,status,payment_phase_status,advance_amount,caution_amount',
        ])->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $applications]);
    }

    /**
     * Mettre à jour son dossier (upload documents, signer contrat).
     * PUT /api/prospect/applications/{id}
     */
    public function updateApplication(Request $request, int $applicationId): JsonResponse
    {
        $request->validate([
            'situation_professionnelle' => 'nullable|in:cdi,cdd,independant,etudiant,retraite,sans_emploi',
            'revenus_mensuels'          => 'nullable|numeric|min:0',
            'has_garant'                => 'boolean',
            'notes'                     => 'nullable|string|max:1000',
            'signed_by_applicant'       => 'nullable|boolean',
            'documents'                 => 'nullable|array',
            'documents.*.type'          => 'required_with:documents|string',
            'documents.*.path'          => 'required_with:documents|string',
        ]);

        $user        = $request->user();
        $application = RentalApplication::where('id', $applicationId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'under_review']) // Ne peut plus modifier si validé/rejeté
            ->firstOrFail();

        $updates = $request->only([
            'situation_professionnelle',
            'revenus_mensuels',
            'has_garant',
            'notes',
        ]);

        // Documents : fusionner avec les existants (ne pas écraser)
        if ($request->has('documents')) {
            $currentDocs = $application->documents ?? [];
            $newDocs = $request->documents;
            // Indexer par type pour éviter les doublons
            $merged = collect($currentDocs)->keyBy('type')
                ->merge(collect($newDocs)->keyBy('type'))
                ->values()
                ->toArray();
            $updates['documents'] = $merged;
        }

        // Signature du contrat
        if ($request->boolean('signed_by_applicant')) {
            $updates['signed_by_applicant'] = true;
            $updates['signed_at'] = now();
        }

        // Si tous les documents requis sont là + contrat signé → passer en under_review
        $application->fill($updates);
        if ($application->signed_by_applicant && !empty($application->documents)) {
            $application->status = 'under_review';
        }
        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Dossier mis à jour.',
            'data'    => $application->fresh(['property:id,title,city', 'agent:id,name']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 3 : SUIVI DU PAIEMENT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Voir son contrat de location en cours (Phase 3).
     * GET /api/prospect/rentals/{id}
     */
    public function showRental(int $rentalId): JsonResponse
    {
        $user   = request()->user();
        $rental = Rental::with([
            'property:id,title,city,location,price',
            'property.primaryImage',
            'agent:id,name,phone',
        ])->where('id', $rentalId)
            ->where('tenant_id', $user->id)
            ->firstOrFail();

        $rental->payment_label = match ($rental->payment_phase_status) {
            'pending'   => 'Paiement en attente',
            'paid'      => 'Paiement effectué — en attente de confirmation agent',
            'confirmed' => 'Paiement confirmé — location active',
            default     => $rental->payment_phase_status,
        };

        return response()->json(['success' => true, 'data' => $rental]);
    }

    /**
     * Voir tous ses contrats (locations).
     * GET /api/prospect/rentals
     */
    public function myRentals(Request $request): JsonResponse
    {
        $user = $request->user();

        $rentals = Rental::with([
            'property:id,title,city,price',
            'property.primaryImage',
        ])->where('tenant_id', $user->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $rentals]);
    }

    /**
     * Effectuer/Signaler le paiement initial.
     * POST /api/prospect/rentals/{id}/pay-initial
     */
    public function payInitial(Request $request, int $rentalId): JsonResponse
    {
        $user = $request->user();

        $rental = Rental::with('property')
            ->where('tenant_id', $user->id)
            ->where('payment_phase_status', 'pending')
            ->findOrFail($rentalId);

        $totalAmount = $rental->advance_amount + $rental->caution_amount;

        $notchPayService = app(\App\Services\NotchPayService::class);
        $reference = 'RENTAL_' . $rental->id . '_' . time();

        \App\Models\Transaction::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'type' => 'payment',
            'amount' => $totalAmount,
            'currency' => 'XAF',
            'status' => 'pending',
            'payment_method' => 'momo',
            'metadata' => [
                'action' => 'rental_payment',
                'rental_id' => $rental->id,
            ],
        ]);

        try {
            $paymentToken = $notchPayService->initializePayment([
                'amount' => $totalAmount,
                'email' => $user->email,
                'reference' => $reference,
                'description' => "Paiement initial pour la location: {$rental->property->title}",
                'metadata' => [
                    'action' => 'rental_payment',
                    'rental_id' => $rental->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => $paymentToken->authorization_url,
                'reference' => $reference,
                'message' => 'Paiement initialisé.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function enrichVisit(Visit $visit): Visit
    {
        $visit->can_apply    = $visit->status === 'completed'
            && ! RentalApplication::where('visit_id', $visit->id)->exists();

        $visit->phase_label = match ($visit->status) {
            'pending'   => $visit->fee_payment_status === 'paid'
                ? 'En attente de confirmation agent'
                : 'En attente de paiement des frais',
            'confirmed' => 'Visite confirmée — en attente de votre confirmation',
            'completed' => $visit->can_apply
                ? 'Visite terminée — soumettez votre dossier'
                : 'Visite terminée — dossier déjà soumis',
            'cancelled' => 'Visite annulée',
            default     => $visit->status,
        };

        return $visit;
    }
}
