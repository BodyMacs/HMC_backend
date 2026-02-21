<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Transaction;
use App\Models\Intervention;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    /**
     * Dashboard Statistics
     */
    public function dashboard()
    {
        $user = Auth::user();

        $activeRental = Rental::with('property.primaryImage')
            ->where('tenant_id', $user->id)
            ->where('status', 'active')
            ->first();

        $stats = [
            'active_rentals_count' => Rental::where('tenant_id', $user->id)->where('status', 'active')->count(),
            'total_spent' => Transaction::where('user_id', $user->id)
                ->where('type', 'payment')
                ->where('status', 'successful')
                ->sum('amount'),
            'pending_interventions_count' => Intervention::where('requester_id', $user->id)
                ->where('status', 'pending')
                ->count(),
            'favorites_count' => Favorite::where('user_id', $user->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'active_rental' => $activeRental,
                'recent_transactions' => Transaction::where('user_id', $user->id)
                    ->latest()
                    ->take(5)
                    ->get(),
                'recent_interventions' => Intervention::with('service')
                    ->where('requester_id', $user->id)
                    ->latest()
                    ->take(3)
                    ->get(),
                'recent_visits' => Visit::with('property')
                    ->where('user_id', $user->id)
                    ->latest()
                    ->take(3)
                    ->get(),
            ]
        ]);
    }

    /**
     * List all rentals
     */
    public function rentals()
    {
        $user = Auth::user();
        $rentals = Rental::with(['property.primaryImage', 'property.owner'])
            ->where('tenant_id', $user->id)
            ->orderByRaw("FIELD(status, 'active', 'finished', 'cancelled')")
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rentals
        ]);
    }

    /**
     * List all payments/transactions
     */
    public function payments()
    {
        $user = Auth::user();
        $transactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Get list of interventions
     */
    public function interventions()
    {
        $user = Auth::user();
        $interventions = Intervention::with('service.category')
            ->where('requester_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $interventions
        ]);
    }

    /**
     * Get list of favorites
     */
    public function favorites()
    {
        $user = Auth::user();
        $favorites = Favorite::with('property.primaryImage')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favorites
        ]);
    }

    /**
     * Toggle favorite
     */
    public function toggleFavorite(Request $request)
    {
        $request->validate(['property_id' => 'required|exists:properties,id']);
        $user = Auth::user();

        $favorite = Favorite::where('user_id', $user->id)
            ->where('property_id', $request->property_id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $status = 'removed';
        } else {
            Favorite::create([
                'user_id' => $user->id,
                'property_id' => $request->property_id
            ]);
            $status = 'added';
        }

        return response()->json([
            'success' => true,
            'status' => $status
        ]);
    }

    /**
     * Apply for a rental
     */
    public function apply(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date|after:today',
            'duration_months' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        $user = Auth::user();

        // Check if application already exists
        $existing = Rental::where('tenant_id', $user->id)
            ->where('property_id', $request->property_id)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une demande en cours pour ce bien.'
            ], 422);
        }

        $property = Property::findOrFail($request->property_id);

        $rental = Rental::create([
            'tenant_id' => $user->id,
            'property_id' => $request->property_id,
            'price' => $property->price,
            'status' => 'pending',
            'start_date' => $request->start_date,
            'end_date' => date('Y-m-d', strtotime($request->start_date . " + {$request->duration_months} months")),
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande de location a été envoyée avec succès.',
            'data' => $rental
        ]);
    }

    /**
     * Book a property visit
     */
    public function bookVisit(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'scheduled_at' => 'required|date|after:now',
            'notes' => 'nullable|string'
        ]);

        $user = Auth::user();

        // Check if a visit is already scheduled for this property by this user
        $existing = Visit::where('user_id', $user->id)
            ->where('property_id', $request->property_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une visite programmée ou en attente pour ce bien.'
            ], 422);
        }

        Visit::create([
            'property_id' => $request->property_id,
            'user_id' => $user->id,
            'scheduled_at' => $request->scheduled_at,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande de visite a été enregistrée.'
        ]);
    }

    /**
     * Create a new intervention request
     */
    public function createIntervention(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'service_id' => 'required|exists:services,id',
            'scheduled_at' => 'required|date|after:now',
            'notes' => 'required|string'
        ]);

        $user = Auth::user();

        // Check if the user is a tenant of this property
        $isTenant = Rental::where('tenant_id', $user->id)
            ->where('property_id', $request->property_id)
            ->whereIn('status', ['active', 'pending']) // Allowing pending for demo or specific flow
            ->exists();

        if (!$isTenant) {
            return response()->json([
                'success' => false,
                'message' => "Vous n'êtes pas autorisé à demander une intervention pour ce bien."
            ], 403);
        }

        // For real app, maybe only active tenants. But for now let's be flexible or check if user is logged in.

        $intervention = Intervention::create([
            'property_id' => $request->property_id,
            'requester_id' => $user->id,
            'service_id' => $request->service_id,
            'scheduled_at' => $request->scheduled_at,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande d\'intervention a été soumise.',
            'data' => $intervention
        ]);
    }

    /**
     * Get tenant profile
     */
    public function profile()
    {
        return response()->json([
            'success' => true,
            'data' => Auth::user()
        ]);
    }

    /**
     * Update tenant profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:100',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'data' => $user->fresh()
        ]);
    }
}
