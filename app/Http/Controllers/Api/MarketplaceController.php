<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\MarketplaceOrder;
use App\Models\Transaction;
use App\Services\NotchPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceController extends Controller
{
    protected $notchPayService;

    public function __construct(NotchPayService $notchPayService)
    {
        $this->notchPayService = $notchPayService;
    }

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
            'discount' => $p->old_price ? round((($p->old_price - (float)$p->price) / (float)$p->old_price) * 100) : null,
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
                'image' => $s->category ? $s->category->icon : 'fas fa-tools',
                'category' => $s->category ? $s->category->name : 'Services',
                'rating' => 5.0,
                'reviews' => 0,
                'location' => $s->provider ? $s->provider->city : 'Cameroun',
                'isNew' => false,
                'discount' => null,
                'type' => 'service',
            ]);
        }

        // Merge and sort
        $allItems = $products->concat($services)->sortByDesc('id');

        // Pagination
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'price'       => 'required|numeric|min:0',
            'old_price'   => 'nullable|numeric|min:0',
            'category'    => 'required|string|max:100',
            'condition'   => 'required|in:Neuf,Excellent,Bon état,Occasion',
            'stock'       => 'required|integer|min:0',
            'location'    => 'required|string|max:150',
            'image'       => 'nullable|image|max:5120',
            'contact_phone'  => 'nullable|string|max:30',
            'contact_whatsapp' => 'nullable|string|max:30',
            'delivery_available' => 'nullable|boolean',
            'delivery_fee'   => 'nullable|numeric|min:0',
        ]);

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

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'delivery_fee' => 'nullable|numeric|min:0',
        ]);

        $product = Product::find($validated['product_id']);
        
        if ($product->stock < $validated['quantity']) {
            return response()->json(['success' => false, 'message' => 'Stock insuffisant'], 400);
        }

        $totalAmount = ($product->price * $validated['quantity']) + ($validated['delivery_fee'] ?? 0);
        $reference = 'MKT-' . strtoupper(Str::random(12));

        try {
            return DB::transaction(function () use ($product, $validated, $totalAmount, $reference) {
                $order = MarketplaceOrder::create([
                    'buyer_id'    => Auth::id(),
                    'product_id'  => $product->id,
                    'quantity'    => $validated['quantity'],
                    'amount'      => $totalAmount,
                    'delivery_fee'=> $validated['delivery_fee'] ?? 0,
                    'status'      => 'pending',
                    'transaction_reference' => $reference,
                    'metadata'    => ['product_name' => $product->name]
                ]);

                Transaction::create([
                    'user_id'    => Auth::id(),
                    'reference'  => $reference,
                    'type'       => 'payment',
                    'amount'     => $totalAmount,
                    'status'     => 'pending',
                    'payment_method' => 'momo',
                    'metadata'   => [
                        'action'   => 'marketplace_purchase',
                        'order_id' => $order->id,
                        'product_id' => $product->id
                    ]
                ]);

                $payment = $this->notchPayService->initializePayment([
                    'amount'      => $totalAmount,
                    'email'       => Auth::user()->email,
                    'reference'   => $reference,
                    'description' => "Achat Marketplace: {$product->name}",
                    'metadata'    => [
                        'action'   => 'marketplace_purchase',
                        'order_id' => $order->id
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'order_id'    => $order->id,
                        'reference'   => $reference,
                        'payment_url' => $payment->authorization_url,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkoutCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $reference = 'CART-' . strtoupper(Str::random(12));
        $totalAmount = 0;
        $orderData = [];

        try {
            return DB::transaction(function () use ($validated, $reference, &$totalAmount, &$orderData) {
                foreach ($validated['items'] as $cartItem) {
                    $product = Product::find($cartItem['id']);
                    
                    if ($product->stock < $cartItem['quantity']) {
                        throw new \Exception("Stock insuffisant pour le produit: {$product->name}");
                    }

                    $itemTotal = $product->price * $cartItem['quantity'];
                    $totalAmount += $itemTotal;

                    // Créer l'ordre individuel
                    $order = MarketplaceOrder::create([
                        'buyer_id'    => Auth::id(),
                        'product_id'  => $product->id,
                        'quantity'    => $cartItem['quantity'],
                        'amount'      => $itemTotal,
                        'delivery_fee'=> 0, // À raffiner si besoin par item
                        'status'      => 'pending',
                        'transaction_reference' => $reference,
                        'metadata'    => ['product_name' => $product->name, 'is_cart' => true]
                    ]);

                    $orderData[] = $order->id;
                }

                // 2. Créer la transaction globale unique
                Transaction::create([
                    'user_id'    => Auth::id(),
                    'reference'  => $reference,
                    'type'       => 'payment',
                    'amount'     => $totalAmount,
                    'status'     => 'pending',
                    'payment_method' => 'momo',
                    'metadata'   => [
                        'action'   => 'marketplace_cart_purchase',
                        'order_ids' => $orderData,
                    ]
                ]);

                // 3. Initialiser NotchPay
                $payment = $this->notchPayService->initializePayment([
                    'amount'      => $totalAmount,
                    'email'       => Auth::user()->email,
                    'reference'   => $reference,
                    'description' => "Commande Panier Marketplace (" . count($orderData) . " articles)",
                    'metadata'    => [
                        'action'   => 'marketplace_cart_purchase',
                        'order_ids' => $orderData
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'order_ids'   => $orderData,
                        'reference'   => $reference,
                        'payment_url' => $payment->authorization_url,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function myOrders(): JsonResponse
    {
        $orders = MarketplaceOrder::with('product')
            ->where('buyer_id', Auth::id())
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }

    public function mySales(): JsonResponse
    {
        $orders = MarketplaceOrder::with(['product', 'buyer'])
            ->whereHas('product', function($q) {
                $q->where('user_id', Auth::id());
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }

    public function confirmDelivery($id): JsonResponse
    {
        $order = MarketplaceOrder::where('id', $id)
            ->where('buyer_id', Auth::id())
            ->firstOrFail();

        if ($order->status !== 'paid_escrow' && $order->status !== 'shipped') {
            return response()->json(['success' => false, 'message' => 'Statut invalide pour confirmation'], 400);
        }

        $order->update(['status' => 'delivered']);

        \App\Models\Notification::create([
            'user_id' => $order->product->user_id,
            'title'   => 'Produit reçu !',
            'message' => "Le client a confirmé la réception de {$order->product->name}. Vos fonds seront libérés sous peu.",
            'type'    => 'success',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Réception confirmée avec succès.'
        ]);
    }

    public function categories(): JsonResponse
    {
        $productCats = Product::select('category')->distinct()->pluck('category')->map(fn ($c) => [
            'id' => $c,
            'name' => ucfirst((string)$c),
            'icon' => $this->getIconForCategory((string)$c),
        ]);

        $cats = collect([
            ['id' => 'all', 'name' => 'Tout', 'icon' => 'fas fa-th-large'],
        ])->concat($productCats);

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

    private function getIconForCategory(string $c): string
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
