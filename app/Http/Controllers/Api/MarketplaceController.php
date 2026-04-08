<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category', 'all');
        $search = $request->query('search');

        // Products query
        $productsQuery = Product::where('status', 'active');

        if ($search) {
            $productsQuery->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category !== 'all' && $category !== 'services') {
            $productsQuery->where('category', $category);
        }

        $products = $productsQuery->latest()->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => $p->price,
            'oldPrice' => $p->old_price,
            'image' => $p->image,
            'category' => $p->category,
            'rating' => $p->rating,
            'reviews' => $p->reviews_count,
            'location' => $p->location,
            'isNew' => $p->is_new,
            'discount' => $p->old_price ? round((($p->old_price - $p->price) / $p->old_price) * 100) : null,
            'type' => 'product',
        ]);

        // Services query
        $services = collect();
        if ($category === 'all' || $category === 'services') {
            $servicesQuery = Service::with('category', 'provider')->where('status', 'active');

            if ($search) {
                $servicesQuery->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $services = $servicesQuery->latest()->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->title,
                'price' => $s->base_price,
                'oldPrice' => null,
                'image' => $s->category ? $s->category->icon : 'fas fa-tools', // UI uses image or icon
                'category' => $s->category ? $s->category->name : 'Services',
                'rating' => 5.0, // Placeholder
                'reviews' => 0, // Placeholder
                'location' => $s->provider ? $s->provider->city : 'Cameroun',
                'isNew' => false,
                'discount' => null,
                'type' => 'service',
            ]);
        }

        // Merge and sort if necessary, or just return both
        // For the UI grid, it's better to merge them or provide separate arrays
        $allItems = $products->concat($services)->sortByDesc('id');

        return response()->json([
            'success' => true,
            'data' => $allItems->values(),
        ]);
    }

    public function show($id, Request $request): JsonResponse
    {
        $type = $request->query('type', 'product');

        if ($type === 'service') {
            $item = Service::with('category', 'provider')->find($id);
            if (! $item) {
                return response()->json(['success' => false, 'message' => 'Service non trouvé'], 404);
            }

            $data = [
                'id' => $item->id,
                'name' => $item->title,
                'description' => $item->description,
                'price' => $item->base_price,
                'image' => $item->category ? $item->category->icon : 'fas fa-tools',
                'category' => $item->category ? $item->category->name : 'Services',
                'location' => $item->provider ? $item->provider->city : 'Cameroun',
                'provider' => $item->provider,
                'type' => 'service',
                'rating' => 5.0,
                'reviews' => [],
            ];
        } else {
            $item = Product::find($id);
            if (! $item) {
                return response()->json(['success' => false, 'message' => 'Produit non trouvé'], 404);
            }

            $data = [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => $item->price,
                'oldPrice' => $item->old_price,
                'image' => $item->image,
                'category' => $item->category,
                'location' => $item->location,
                'isNew' => $item->is_new,
                'type' => 'product',
                'rating' => $item->rating,
                'reviews' => [],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function categories(): JsonResponse
    {
        // Get unique product categories
        $productCats = Product::select('category')->distinct()->pluck('category')->map(fn ($c) => [
            'id' => $c,
            'name' => ucfirst($c),
            'icon' => $this->getIconForCategory($c),
        ]);

        // Add services special category
        $cats = collect([
            ['id' => 'all', 'name' => 'Tout', 'icon' => 'fas fa-th-large'],
        ])->concat($productCats);

        // Add real service categories from database
        $serviceCats = ServiceCategory::all()->map(fn ($sc) => [
            'id' => $sc->id,
            'name' => $sc->name,
            'icon' => $sc->icon ?: 'fas fa-tools',
        ]);

        return response()->json([
            'success' => true,
            'data' => $cats->concat($serviceCats)->unique('id')->values(),
        ]);
    }

    private function getIconForCategory($c)
    {
        $map = [
            'meubles' => 'fas fa-couch',
            'furniture' => 'fas fa-couch',
            'decoration' => 'fas fa-paint-brush',
            'lighting' => 'fas fa-lightbulb',
            'élec' => 'fas fa-blender',
            'appliances' => 'fas fa-blender',
            'security' => 'fas fa-shield-alt',
            'sécurité' => 'fas fa-shield-alt',
        ];

        foreach ($map as $key => $icon) {
            if (stripos($c, $key) !== false) {
                return $icon;
            }
        }

        return 'fas fa-tag';
    }
}
