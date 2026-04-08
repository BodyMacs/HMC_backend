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

        // Filter by Category (via their services)
        if ($request->has('category_id')) {
            $query->whereHas('services', function ($q) use ($request): void {
                $q->where('category_id', $request->category_id);
            });
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $providers = $query->latest()->paginate(12);

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
