<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyReview;
use App\Models\Property;
use App\Models\Visit;
use App\Models\Rental;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyReviewController extends Controller
{
    /**
     * GET /api/properties/{identifier}/reviews
     * Liste les avis approuvés d'un bien, avec les agrégats de notation.
     */
    public function index($identifier): JsonResponse
    {
        $property = Property::where(function ($q) use ($identifier) {
            is_numeric($identifier)
                ? $q->where('id', $identifier)
                : $q->where('slug', $identifier);
        })->firstOrFail();

        $reviews = PropertyReview::with('user')
            ->where('property_id', $property->id)
            ->approved()
            ->latest()
            ->paginate(10);

        // Agrégats
        $stats = PropertyReview::where('property_id', $property->id)
            ->approved()
            ->selectRaw('
                COUNT(*) as total,
                ROUND(AVG(rating), 1) as average,
                SUM(rating = 5) as five,
                SUM(rating = 4) as four,
                SUM(rating = 3) as three,
                SUM(rating = 2) as two,
                SUM(rating = 1) as one
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $reviews,
            'stats'   => $stats,
        ]);
    }

    /**
     * POST /api/properties/{identifier}/reviews
     * Soumettre un avis (authentifié).
     */
    public function store(Request $request, $identifier): JsonResponse
    {
        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'nullable|string|max:120',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        $property = Property::where(function ($q) use ($identifier) {
            is_numeric($identifier)
                ? $q->where('id', $identifier)
                : $q->where('slug', $identifier);
        })->firstOrFail();

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Vérifier si l'utilisateur a déjà laissé un avis
        $existing = PropertyReview::where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà laissé un avis pour ce bien.',
            ], 422);
        }

        // Chercher une visite complétée ou une location active
        $completedVisit = Visit::where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        $isVerifiedTenant = Rental::where('property_id', $property->id)
            ->where('tenant_id', $user->id)
            ->where('status', 'active')
            ->exists();

        $review = PropertyReview::create([
            'property_id'        => $property->id,
            'user_id'            => $user->id,
            'visit_id'           => $completedVisit?->id,
            'rating'             => $validated['rating'],
            'title'              => $validated['title'] ?? null,
            'comment'            => $validated['comment'],
            'status'             => 'approved', // auto-approve for now
            'is_verified_tenant' => $isVerifiedTenant,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre avis a été publié avec succès.',
            'data'    => $review->load('user'),
        ], 201);
    }

    /**
     * PUT /api/reviews/{id}
     * Modifier son propre avis.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'nullable|string|max:120',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        /** @var \App\Models\User $user */
        $user   = $request->user();
        $review = PropertyReview::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $review->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Avis mis à jour.',
            'data'    => $review->fresh('user'),
        ]);
    }

    /**
     * DELETE /api/reviews/{id}
     * Supprimer son propre avis (ou admin).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user   = $request->user();
        $review = PropertyReview::findOrFail($id);

        if ($review->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Avis supprimé.',
        ]);
    }

    /**
     * GET /api/reviews/my/{propertyId}
     * Récupère l'avis de l'utilisateur connecté pour un bien.
     */
    public function myReview(Request $request, $identifier): JsonResponse
    {
        $property = Property::where(function ($q) use ($identifier) {
            is_numeric($identifier)
                ? $q->where('id', $identifier)
                : $q->where('slug', $identifier);
        })->firstOrFail();

        $review = PropertyReview::where('property_id', $property->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $review,
        ]);
    }
}
