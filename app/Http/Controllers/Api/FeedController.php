<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Property;
use App\Models\ServicePost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Social Feed — agrège toutes les sections de la plateforme
     * GET /api/feed
     * GET /api/feed?page=2
     */
    public function index(Request $request): JsonResponse
    {
        $page    = (int) $request->query('page', 1);
        $perPage = 8; // items par type par page

        // ── 1. Biens immobiliers récents ─────────────────────────────────────
        $properties = Property::with(['primaryImage', 'images'])
            ->withCount(['favorites'])
            ->latest()
            ->skip(($page - 1) * 2)
            ->take(2)
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'feed_type'      => 'property',
                'slug'           => $p->slug,
                'title'          => $p->title,
                'price'          => $p->price,
                'city'           => $p->city,
                'neighborhood'   => $p->neighborhood ?? null,
                'category'       => $p->category ?? 'Immobilier',
                'bedrooms'       => $p->bedrooms ?? 0,
                'bathrooms'      => $p->bathrooms ?? 0,
                'area'           => $p->area ?? 0,
                'image'          => $p->primaryImage?->path ?? null,
                'all_images'     => $p->images->map(fn ($img) => $img->path)->toArray(),
                'favorites_count'=> $p->favorites_count ?? 0,
                'shares_count'   => $p->shares_count ?? 0,
                'date'           => $p->created_at?->diffForHumans() ?? 'Récemment',
                'status'         => $p->status ?? 'available',
            ]);

        // ── 2. Produits Marketplace récents ──────────────────────────────────
        $products = Product::where('status', 'active')
            ->latest()
            ->skip(($page - 1) * 1)
            ->take(1)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'feed_type'   => 'product',
                'name'        => $p->name,
                'price'       => $p->price,
                'old_price'   => $p->old_price,
                'image'       => $p->image,
                'category'    => $p->category,
                'location'    => $p->location,
                'rating'      => $p->rating ?? 0,
                'reviews'     => $p->reviews_count ?? 0,
                'is_new'      => (bool) ($p->is_new ?? false),
                'discount'    => $p->old_price
                    ? round((($p->old_price - $p->price) / $p->old_price) * 100)
                    : null,
                'date'        => $p->created_at?->diffForHumans() ?? 'Récemment',
            ]);

        // ── 3. Demandes de prestataires (Job Board) ────────────────────────
        $serviceRequests = ServicePost::with(['client', 'category'])
            ->where('status', 'open')
            ->withCount('responses')
            ->latest()
            ->skip(($page - 1) * 1)
            ->take(1)
            ->get()
            ->map(fn ($sp) => [
                'id'              => $sp->id,
                'feed_type'       => 'service_request',
                'title'           => $sp->title,
                'description'     => $sp->description,
                'city'            => $sp->city,
                'neighborhood'    => $sp->neighborhood ?? null,
                'category'        => $sp->category?->name ?? 'Services',
                'category_icon'   => $sp->category?->icon ?? 'fas fa-tools',
                'urgency'         => $sp->urgency ?? 'medium',
                'min_budget'      => $sp->min_budget,
                'max_budget'      => $sp->max_budget,
                'responses_count' => $sp->responses_count ?? 0,
                'client_name'     => $sp->client?->name ?? 'Anonyme',
                'client_avatar'   => $sp->client?->avatar ?? null,
                'date'            => $sp->created_at?->diffForHumans() ?? 'Récemment',
            ]);

        // ── 4. Prestataires recommandés (profils) ────────────────────────
        $providers = User::with(['services.category'])
            ->whereJsonContains('roles', 'prestataire')
            ->where('status', 'active')
            ->latest()
            ->skip(($page - 1) * 1)
            ->take(1)
            ->get()
            ->map(fn ($u) => [
                'id'              => $u->id,
                'feed_type'       => 'provider',
                'name'            => $u->name,
                'city'            => $u->city ?? 'Cameroun',
                'avatar'          => $u->avatar ?? null,
                'bio'             => $u->bio ?? null,
                'services'        => $u->services->map(fn ($s) => [
                    'title'    => $s->title,
                    'category' => $s->category?->name ?? 'Service',
                    'icon'     => $s->category?->icon ?? 'fas fa-tools',
                ])->take(3)->toArray(),
                'services_count'  => $u->services->count(),
                'rating'          => 4.5, // placeholder — à remplacer par avg reviews
                'date'            => $u->created_at?->diffForHumans() ?? 'Récemment',
            ]);

        // ── 5. Stories — 6 biens récents pour la barre de stories ─────────
        $stories = Property::with(['primaryImage'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'slug'  => $p->slug,
                'title' => $p->title,
                'city'  => $p->city,
                'image' => $p->primaryImage?->path ?? null,
                'price' => $p->price,
            ]);

        // ── 6. Stats globales de la plateforme ────────────────────────────
        $stats = [
            'total_properties'    => Property::count(),
            'total_products'      => Product::where('status', 'active')->count(),
            'active_providers'    => User::whereJsonContains('roles', 'prestataire')
                ->where('status', 'active')->count(),
            'open_service_requests' => ServicePost::where('status', 'open')->count(),
        ];

        // ── Vérifier s'il y a encore des données (has_more) ───────────────
        $hasMore = $properties->count() === 2
            || $products->count() === 1
            || $serviceRequests->count() === 1
            || $providers->count() === 1;

        // ── Mélange organique pour un vrai fil d'actualité social ────────
        $allItems = collect()
            ->concat($properties)
            ->concat($products)
            ->concat($serviceRequests)
            ->concat($providers)
            ->shuffle()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items'           => $allItems,
                'properties'      => $properties,
                'products'        => $products,
                'service_requests'=> $serviceRequests,
                'providers'       => $providers,
                'stories'         => $page === 1 ? $stories : [], // stories seulement page 1
                'stats'           => $page === 1 ? $stats : null,
            ],
            'meta' => [
                'page'     => $page,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
