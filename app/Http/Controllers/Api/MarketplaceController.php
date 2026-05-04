<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'id'       => $p->id,
            'name'     => $p->name,
            'price'    => $p->price,
            'oldPrice' => $p->old_price,
            'image'    => $p->image,
            'category' => $p->category,
            'condition'=> $p->condition ?? 'Neuf',
            'stock'    => $p->stock ?? 1,
            'rating'   => $p->rating,
            'reviews'  => $p->reviews_count,
            'location' => $p->location,
            'isNew'    => $p->is_new,
            'discount' => $p->old_price ? round((($p->old_price - $p->price) / $p->old_price) * 100) : null,
            'type'     => 'product',
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

        // Merge and sort
        $allItems = $products->concat($services)->sortByDesc('id');

        // Pagination manuelle
        $perPage = (int) $request->query('per_page', 12);
        $page = (int) $request->query('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedItems = new LengthAwarePaginator(
            $allItems->slice($offset, $perPage)->values(),
            $allItems->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => $paginatedItems,
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
                'id'          => $item->id,
                'name'        => $item->name,
                'description' => $item->description,
                'price'       => $item->price,
                'oldPrice'    => $item->old_price,
                'image'       => $item->image,
                'category'    => $item->category,
                'condition'   => $item->condition ?? 'Neuf',
                'stock'       => $item->stock ?? 1,
                'location'    => $item->location,
                'isNew'       => $item->is_new,
                'type'        => 'product',
                'rating'      => $item->rating,
                'reviews'     => [],
                'seller'      => $item->user ? [
                    'name'   => $item->user->name,
                    'avatar' => $item->user->avatar,
                ] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Créer un nouveau produit (utilisateur authentifié)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'price'       => 'required|numeric|min:0',
            'old_price'   => 'nullable|numeric|min:0',
            'category'    => 'required|string|max:100',
            'condition'   => 'required|in:Neuf,Occasion,Reconditionné,Excellent,Bon état',
            'stock'       => 'required|integer|min:0',
            'location'    => 'required|string|max:150',
            'image'       => 'nullable|image|max:5120',  // 5 MB max
            'contact_phone'  => 'nullable|string|max:30',
            'contact_whatsapp' => 'nullable|string|max:30',
            'delivery_available' => 'nullable|boolean',
            'delivery_fee'   => 'nullable|numeric|min:0',
        ]);

        // Upload image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'products/' . Str::uuid() . '.' . $file->extension();
            $imagePath = $file->storePublicly($filename, 'public');
            $imagePath = Storage::url($imagePath);
        }

        $product = Product::create([
            'name'        => $validated['name'],
            'description' => $validated['description'],
            'price'       => $validated['price'],
            'old_price'   => $validated['old_price'] ?? null,
            'category'    => $validated['category'],
            'condition'   => $validated['condition'],
            'stock'       => $validated['stock'],
            'location'    => $validated['location'],
            'image'       => $imagePath,
            'user_id'     => Auth::id(),
            'status'      => 'active',
            'is_new'      => true,
            'contact_phone'      => $validated['contact_phone'] ?? null,
            'contact_whatsapp'   => $validated['contact_whatsapp'] ?? null,
            'delivery_available' => $validated['delivery_available'] ?? false,
            'delivery_fee'       => $validated['delivery_fee'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit publié avec succès !',
            'data'    => ['id' => $product->id],
        ], 201);
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
