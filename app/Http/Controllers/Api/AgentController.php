<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\User;
use App\Models\Visit;
use App\Models\PropertyRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'pending_publication_missions' => PropertyRequest::where('agent_id', $agent->id)->where('status', 'assigned')->count(),
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
     * Agenda de l'agent : liste des visites confirmées ou prévues
     */
    public function agenda(Request $request): JsonResponse
    {
        $agent = $request->user();

        // On récupère toutes les visites de l'agent, confirmées ou en attente,
        // à partir du début du mois courant jusqu'à 3 mois dans le futur
        $visits = Visit::with(['property:id,title,city', 'visitor:id,name,phone'])
            ->where('agent_id', $agent->id)
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->where('scheduled_at', '>=', now()->startOfMonth())
            ->where('scheduled_at', '<=', now()->addMonths(3))
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $visits]);
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
                'publication_missions' => PropertyRequest::with('bailleur')->where('agent_id', $agent->id)->where('status', 'assigned')->latest()->get(),
            ],
        ]);
    }

    /**
     * Liste des missions d'audit/publication assignées à l'agent
     */
    public function publicationMissions(Request $request): JsonResponse
    {
        $agent = $request->user();
        $missions = PropertyRequest::with('bailleur')->where('agent_id', $agent->id)->latest()->paginate(15);
        return response()->json(['success' => true, 'data' => $missions]);
    }

    /**
     * Détails d'une mission d'audit spécifique
     */
    public function showPublicationMission(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $mission = PropertyRequest::with('bailleur')->where('agent_id', $agent->id)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $mission]);
    }

    /**
     * Programmer la date de visite/audit pour une mission.
     */
    public function schedulePublicationMission(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'agent_notes'  => 'nullable|string|max:500',
        ]);

        $agent = $request->user();
        $mission = PropertyRequest::where('agent_id', $agent->id)->findOrFail($id);

        $mission->update([
            'scheduled_at' => $validated['scheduled_at'],
            'agent_notes'  => $validated['agent_notes'],
            'status'       => 'assigned',
            'bailleur_confirmed_at' => null,
            'bailleur_declined_at' => null,
            'bailleur_suggested_at' => null,
            'bailleur_notes' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'L\'audit a été programmé pour le ' . $mission->scheduled_at->format('d/m/Y H:i'),
            'data'    => $mission
        ]);
    }

    /**
     * Finaliser la publication d'un bien après audit terrain
     * POST /api/agent/publication-missions/{id}/complete
     */
    public function completePublication(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'category'    => 'required|string',
            'type'        => 'required|in:rent,sale',
            'price'       => 'required|numeric',
            'city'        => 'required|string',
            'location'    => 'required|string',
            'description' => 'required|string',
            'bedrooms'    => 'nullable|integer',
            'bathrooms'   => 'nullable|integer',
            'area'        => 'nullable|numeric',
            'etat'        => 'required|string',
            'amenities'   => 'nullable|string',
            'images'      => 'required|array|min:1',
            'images.*'    => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $agent = $request->user();
        $propRequest = PropertyRequest::where('agent_id', $agent->id)->findOrFail($id);

        if ($propRequest->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Ce bien est déjà publié.'], 422);
        }

        return DB::transaction(function () use ($validated, $propRequest, $request, $agent) {
            // 1. Créer le bien réel
            $property = Property::create([
                'user_id'     => $propRequest->user_id, // Le bailleur
                'agent_id'    => $agent->id,            // L'agent qui gère
                'title'       => $validated['title'],
                'slug'        => Str::slug($validated['title']) . '-' . Str::random(6),
                'type'        => $validated['type'],
                'category'    => $validated['category'],
                'status'      => 'active', // Immédiatement actif car posté par l'agent
                'price'       => $validated['price'],
                'city'        => $validated['city'],
                'location'    => $validated['location'],
                'description' => $validated['description'],
                'bedrooms'    => $validated['bedrooms']  ?? null,
                'bathrooms'   => $validated['bathrooms'] ?? null,
                'area'        => $validated['area']      ?? null,
                'etat'        => $validated['etat'],
                'amenities'   => $validated['amenities'] ?? null,
            ]);

            // 2. Gérer les photos
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('properties', 'public');
                    $property->images()->create([
                        'path'       => $path,
                        'is_primary' => $index === 0,
                        'order'      => $index,
                    ]);
                }
            }

            // 3. Mettre à jour la demande
            $propRequest->update([
                'status'       => 'published',
                'published_at' => now(),
                'visited_at'   => now(), // On considère la visite faite
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Le bien a été publié avec succès sous le compte du bailleur.',
                'data'    => $property->load('images'),
            ]);
        });
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

        // --- NOUVEAU : Bloquer si pas payé ---
        if ($visit->fee_payment_status !== 'paid' && $visit->fee_payment_status !== 'waived') {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez patienter car l\'utilisateur n\'a pas encore réglé les frais de visite (' . Visit::getVisitFee() . ' FCFA).',
            ], 422);
        }

        $visit->update([
            'confirmed_by_agent' => true,
            'visited_at'         => now(),
            'status'             => 'confirmed',
        ]);

        $visit->checkAndComplete();
        $visit->refresh();

        // ── Notification au client ──
        $scheduledAt = $visit->scheduled_at
            ? \Carbon\Carbon::parse($visit->scheduled_at)->format('d/m/Y à H\hi')
            : '';
        NotificationService::visitConfirmed(
            $visit->user_id,
            $visit->property->title ?? 'votre bien',
            $scheduledAt,
            $visit->id
        );

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

            // ── Notification au prospect ──
            NotificationService::applicationValidated(
                $application->user_id,
                $property->title,
                $application->id
            );
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

        // ── Notification au prospect ──
        NotificationService::applicationRejected(
            $application->user_id,
            $application->property->title ?? 'le bien',
            $request->reason,
            $application->id
        );

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

            // ── Notification au locataire ──
            if ($tenant) {
                NotificationService::rentalActivated(
                    $tenant->id,
                    $rental->property->title ?? 'votre logement',
                    $rental->id
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Paiement confirmé. La location est active. Le rôle locataire a été attribué.',
            'data'    => $rental->fresh()->load(['property', 'tenant']),
        ]);
    }

    /**
     * Mettre à jour les disponibilités (horaires de travail) de l'agent.
     */
    public function updateAvailabilities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'availabilities' => 'required|array',
            'availabilities.*.off' => 'required|boolean',
            'availabilities.*.start' => 'nullable|date_format:H:i',
            'availabilities.*.end' => 'nullable|date_format:H:i',
        ]);

        /** @var \App\Models\User $agent */
        $agent = Auth::user();

        $agent->update([
            'availabilities' => $validated['availabilities']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilités mises à jour avec succès.',
            'data'    => $agent->availabilities,
        ]);
    }
}
