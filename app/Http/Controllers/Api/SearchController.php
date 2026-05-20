<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Product;
use App\Models\ServicePost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Get search suggestions across multiple categories.
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [
                    'properties' => [],
                    'products' => [],
                    'services' => [],
                    'providers' => []
                ]
            ]);
        }

        // 1. Properties
        $properties = Property::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('city', 'like', "%{$query}%")
                  ->orWhere('location', 'like', "%{$query}%");
            })
            ->limit(3)
            ->get(['id', 'title', 'slug', 'price', 'city', 'category']);

        // 2. Marketplace Products
        $products = Product::where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get(['id', 'name', 'price', 'category']);

        // 3. Service Missions (ServicePost)
        $services = ServicePost::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->limit(3)
            ->get(['id', 'title', 'min_budget', 'city']);

        // 4. Providers (Users with role prestataire)
        $providers = User::where('role', 'prestataire')
            ->where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get(['id', 'name', 'avatar', 'role']);

        return response()->json([
            'success' => true,
            'data' => [
                'properties' => $properties,
                'products' => $products,
                'services' => $services,
                'providers' => $providers
            ]
        ]);
    }
}
