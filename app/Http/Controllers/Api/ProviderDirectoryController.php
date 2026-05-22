<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDirectoryController extends Controller
{
    /**
     * List all service providers with filters
     */
    public function index(Request $request): JsonResponse
    {
        // Find users who have 'prestataire' in their roles list
        $query = User::with(['services.category'])
            ->whereJsonContains('roles', 'prestataire')
            ->where('status', 'active');

        // Filter by City
        if ($request->has('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        // Filter by Neighborhood
        if ($request->has('neighborhood')) {
            $query->where('neighborhood', 'like', "%{$request->neighborhood}%");
        }

        // Filter by Category_id (Expertise)
        if ($request->has('category_id')) {
            $query->whereHas('services', function ($q) use ($request): void {
                $q->where('category_id', $request->category_id);
            });
        }

        // Filter by Verification (Identity)
        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Sort by
        $sort = $request->query('sort', 'recent');
        switch ($sort) {
            case 'top-rated':
                $query->orderByDesc('rating');
                break;
            case 'experienced':
                $query->orderByDesc('experience_years');
                break;
            default:
                $query->latest();
                break;
        }

        $providers = $query->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $providers
        ]);
    }

    /**
     * Get detail of a specific provider (Public profile)
     */
    public function show(int $id): JsonResponse
    {
        $provider = User::with(['services.category', 'formations'])
            ->whereJsonContains('roles', 'prestataire')
            ->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Prestataire non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $provider
        ]);
    }
}
