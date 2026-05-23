<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        // 0. Stats Marketing (Chiffres "Fake" pour booster la plateforme)
        $statsData = [
            [
                'label' => 'Logements vérifiés',
                'value' => '1,500', 
                'icon' => 'home',
                'suffix' => '+',
                'color' => 'primary',
                'description' => 'Disponibles partout au Cameroun'
            ],
            [
                'label' => 'Agents certifiés',
                'value' => '350',
                'icon' => 'user-tie',
                'suffix' => '+',
                'color' => 'secondary',
                'description' => 'À votre service 24h/7'
            ],
            [
                'label' => 'Produits Marketplace',
                'value' => '800',
                'icon' => 'shopping-bag',
                'suffix' => '+',
                'color' => 'accent',
                'description' => 'Meubles et matériaux'
            ],
            [
                'label' => 'Partenaires Services',
                'value' => '600',
                'icon' => 'tools',
                'suffix' => '+',
                'color' => 'primary',
                'description' => 'Prestataires qualifiés'
            ],
            [
                'label' => 'Utilisateurs actifs',
                'value' => '5,000',
                'icon' => 'users',
                'suffix' => '+',
                'color' => 'secondary',
                'description' => 'Nous font confiance'
            ],
            [
                'label' => 'Satisfaction Client',
                'value' => '98',
                'icon' => 'star',
                'suffix' => '%',
                'color' => 'accent',
                'description' => 'Score exceptionnel'
            ],
        ];

        // 1. New Properties (Format Feed)
        $newProperties = Property::latest()
            ->with(['primaryImage', 'images', 'owner'])
            ->withAvg('reviews', 'rating')
            ->withCount(['favorites', 'reviews', 'comments'])
            ->take(12)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'feed_type'      => 'property',
                'slug'           => $p->slug,
                'title'          => $p->title,
                'price'          => $p->price,
                'city'           => $p->city,
                'location'       => $p->location,
                'category'       => $p->category ?? 'Immobilier',
                'bedrooms'       => $p->bedrooms ?? 0,
                'rooms'          => $p->bedrooms ?? 0,
                'bathrooms'      => $p->bathrooms ?? 0,
                'area'           => $p->area ?? 0,
                'image'          => $p->primaryImage?->path ?? null,
                'all_images'     => $p->images->map(fn($img) => $img->path)->toArray(),
                'rating'         => round((float)($p->reviews_avg_rating ?? 0), 1),
                'review_count'   => $p->reviews_count ?? 0,
                'date'           => $p->created_at->diffForHumans(),
                'owner'          => $p->owner?->name ?? 'Home Cameroon',
            ]);

        // 2. Agents (Format Provider Feed)
        $agents = User::with(['services' => fn($q) => $q->with('category')])
            ->whereJsonContains('roles', 'agent')
            ->take(4)
            ->get()
            ->map(function ($u) {
                $propertiesCount = Property::where('user_id', $u->id)->count();
                return [
                    'id'              => $u->id,
                    'feed_type'       => 'provider',
                    'name'            => $u->name,
                    'city'            => $u->city ?? 'Yaoundé',
                    'avatar'          => $u->avatar ?? null,
                    'bio'             => $u->bio ?? 'Agent immobilier certifié.',
                    'services'        => [
                        ['title' => 'Agent Immobilier', 'category' => 'Immobilier', 'icon' => 'fas fa-briefcase']
                    ],
                    'services_count'  => $propertiesCount,
                    'rating'          => 4.9,
                    'experience_years'=> 3,
                    'date'            => $u->created_at->diffForHumans(),
                ];
            });

        // 3. Services (Prestataires - Format Provider Feed)
        $services = User::with(['services' => fn($q) => $q->with('category')])
            ->whereJsonContains('roles', 'prestataire')
            ->where('status', 'active')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($u) {
                $firstService = $u->services->sortBy('created_at')->first();
                $years = $firstService ? (int)$firstService->created_at->diffInYears(now()) : 2;
                return [
                    'id'              => $u->id,
                    'feed_type'       => 'provider',
                    'name'            => $u->name,
                    'city'            => $u->city ?? 'Cameroun',
                    'avatar'          => $u->avatar ?? null,
                    'bio'             => $u->bio ?? null,
                    'services'        => $u->services->map(fn($s) => [
                        'title'    => $s->title,
                        'category' => $s->category?->name ?? 'Service',
                        'icon'     => $s->category?->icon ?? 'fas fa-tools',
                    ])->take(2)->toArray(),
                    'services_count'  => $u->services->count(),
                    'experience_years'=> $years ?: '< 1',
                    'rating'          => 4.5,
                    'date'            => $u->created_at->diffForHumans(),
                ];
            });

        // 4. Products (Format Product Feed)
        $products = Product::where('status', 'active')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'feed_type'   => 'product',
                'name'        => $p->name,
                'price'       => $p->price,
                'old_price'   => $p->old_price,
                'image'       => $p->image,
                'category'    => $p->category,
                'location'    => $p->location,
                'rating'      => 4.5,
                'reviews'     => 12,
                'is_new'      => true,
                'date'        => $p->created_at->diffForHumans(),
            ]);

        return response()->json([
            'stats' => $statsData,
            'newProperties' => $newProperties,
            'agents' => $agents,
            'services' => $services,
            'products' => $products,
        ]);
    }
}
