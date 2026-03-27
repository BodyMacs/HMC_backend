<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Property;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {

        // 0. Categories
        $categories = Property::selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->get()
            ->map(function ($cat) {
                // Map icons based on category name
                $icon = 'building';
                if (stripos($cat->category, 'chambre') !== false) {
                    $icon = 'bed';
                }
                if (stripos($cat->category, 'studio') !== false) {
                    $icon = 'door-open';
                }
                if (stripos($cat->category, 'appartement') !== false) {
                    $icon = 'building';
                }
                if (stripos($cat->category, 'maison') !== false) {
                    $icon = 'house';
                }
                if (stripos($cat->category, 'villa') !== false) {
                    $icon = 'crown';
                }

                return [
                    'id' => $cat->category, // using name as ID for now
                    'name' => $cat->category,
                    'icon' => $icon,
                    'count' => $cat->count,
                ];
            });

        // 1. Featured/New Properties
        // Note: Using 'owner' relationship from Property model (belongsTo User)
        // Adjust fields based on actual database columns
        $newProperties = Property::latest()
            ->with(['owner', 'primaryImage', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount(['favorites', 'reviews'])
            ->take(10)
            ->get()
            ->map(fn($property) => [
                'id' => $property->id,
                'slug' => $property->slug,
                'title' => $property->title,
                'price' => $property->price,
                'owner' => $property->owner ? $property->owner->name : 'Home Cameroon',
                'date' => $property->created_at->diffForHumans(),
                'rooms' => $property->bedrooms ?? 0,
                'bathrooms' => $property->bathrooms ?? 0,
                'area' => $property->area ?? 0,
                'image' => $property->primaryImage ? $property->primaryImage->path : '/images/categoriebien/appart.jfif',
                'all_images' => $property->images->map(fn($img) => $img->path)->toArray(),
                'city' => $property->city,
                'favorites_count' => $property->favorites_count ?? 0,
                'review_count' => $property->reviews_count ?? 0,
                'shares_count' => $property->shares_count ?? 0,
                'avg_rating' => round((float) ($property->reviews_avg_rating ?? 0), 1),
            ]);

        // 2. Agents
        $agents = User::whereJsonContains('roles', 'agent')
            ->take(4)
            ->get()
            ->map(function ($agent, $index) {
                // Get count of properties for this agent
                $propertiesCount = Property::where('user_id', $agent->id)->count();

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'role' => 'Agent Immobilier',
                    'location' => $agent->city ?? 'Cameroun', // Ensure 'city' column exists in users table or use default
                    'description' => $agent->bio ?? 'Agent immobilier certifié sur Home Cameroon.',
                    'propertiesCount' => $propertiesCount,
                    'rating' => 4.9,
                    'reviews' => 10,
                    'image' => asset('storage/user_profil/agent' . (($index % 4) + 1) . '.jpg'),
                ];
            });

        // 3. Services (Prestataires)
        // Assuming Service model has 'category' relationship
        $services = Service::with('category')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn($service) => [
                'id' => $service->id,
                'title' => $service->title ?? 'Service',
                'subtitle' => $service->category ? $service->category->name : 'Service',
                'description' => $service->description,
                'icon' => $service->category ? $service->category->icon : 'tools',
                'tags' => ['Service', 'Pro'], // Placeholder
            ]);

        // 4. Marketplace Products
        $products = Product::latest()
            ->take(5)
            ->get()
            ->map(fn($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'description' => $product->description,
                'image' => $product->image,
                'badge' => $product->badge,
            ]);

        return response()->json([
            'categories' => $categories,
            'newProperties' => $newProperties,
            'agents' => $agents,
            'services' => $services,
            'products' => $products,
        ]);
    }
}
