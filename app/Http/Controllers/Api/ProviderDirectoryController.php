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
        $query = User::with(['services' => function ($q) {
                $q->orderBy('created_at', 'asc')->with('category');
            }])
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

        // Append computed experience_years based on first service creation date
        $providers->getCollection()->transform(function (User $user) {
            $firstService = $user->services->sortBy('created_at')->first();
            if ($firstService) {
                $user->experience_years = (int) $firstService->created_at->diffInYears(now());
                if ($user->experience_years === 0) {
                    $user->experience_years = '< 1';
                }
            } else {
                $user->experience_years = null;
            }
            return $user;
        });

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
        $provider = User::with(['services' => function ($q) {
                $q->orderBy('created_at', 'asc')->with('category');
            }, 'formations'])
            ->whereJsonContains('roles', 'prestataire')
            ->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Prestataire non trouvé'
            ], 404);
        }

        // Compute experience_years from first service creation date
        $firstService = $provider->services->sortBy('created_at')->first();
        if ($firstService) {
            $years = (int) $firstService->created_at->diffInYears(now());
            $provider->experience_years = $years === 0 ? '< 1' : $years;
        } else {
            $provider->experience_years = null;
        }

        return response()->json([
            'success' => true,
            'data' => $provider
        ]);
    }
}
