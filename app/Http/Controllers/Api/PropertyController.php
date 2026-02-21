<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Property::with(['owner:id,name,avatar', 'primaryImage'])
            ->where('status', 'active');

        // Filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('location', 'like', '%' . $request->search . '%')
                    ->orWhere('city', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }
        // Types multiples (CSV) ou unique
        if ($request->filled('types')) {
            $types = array_filter(array_map('trim', explode(',', $request->types)));
            if (!empty($types)) $query->whereIn('category', $types);
        } elseif ($request->filled('type')) {
            $query->where('category', $request->type);
        }
        // Villes multiples (CSV) ou unique
        if ($request->filled('cities')) {
            $citiesArr = array_filter(array_map('trim', explode(',', $request->cities)));
            if (!empty($citiesArr)) {
                $query->where(function ($q) use ($citiesArr) {
                    foreach ($citiesArr as $c) {
                        $q->orWhere('city', 'like', '%' . $c . '%');
                    }
                });
            }
        } elseif ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('min_rooms') && $request->min_rooms > 0) {
            $query->where('bedrooms', '>=', $request->min_rooms);
        }
        // Filtre état du bien (CSV ou unique)
        if ($request->filled('etats')) {
            $etats = array_filter(array_map('trim', explode(',', $request->etats)));
            if (!empty($etats)) $query->whereIn('etat', $etats);
        } elseif ($request->filled('etat')) {
            $query->where('etat', $request->etat);
        }
        // Filtre commodités (JSON contains) — OR logique : au moins une
        if ($request->filled('amenities')) {
            $amens = array_filter(array_map('trim', explode(',', $request->amenities)));
            if (!empty($amens)) {
                $query->where(function ($q) use ($amens) {
                    foreach ($amens as $a) {
                        $q->orWhere('amenities', 'like', '%' . $a . '%');
                    }
                });
            }
        }

        // Sort
        switch ($request->get('sort', 'recent')) {
            case 'price-asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price-desc':
                $query->orderBy('price', 'desc');
                break;
            case 'area':
                $query->orderBy('area', 'desc');
                break;
            default:
                $query->latest();
                break;
        }

        $properties = $query->paginate(15);

        $user = auth('sanctum')->user();
        $favoriteIds = $user ? $user->favorites()->pluck('property_id')->toArray() : [];

        // Normalize image URL for each property
        $properties->getCollection()->transform(function ($p) use ($favoriteIds) {
            $primaryImage = $p->primaryImage;
            if ($primaryImage) {
                $path = $primaryImage->path;
                // Si c'est déjà une URL absolue (Unsplash etc.), on la garde
                $p->image = str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
            } else {
                $p->image = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800';
            }
            $p->rooms       = $p->bedrooms ?? 0;
            $p->owner       = $p->owner;
            $p->is_favorite = in_array($p->id, $favoriteIds);
            return $p;
        });

        // Sidebar aggregates
        $baseQuery = Property::where('status', 'active');

        $typeAggregates = (clone $baseQuery)
            ->selectRaw('category as value, count(*) as count')
            ->groupBy('category')->orderBy('count', 'desc')
            ->get()
            ->map(fn($t) => ['label' => $t->value, 'value' => $t->value, 'count' => $t->count]);

        $cityAggregates = (clone $baseQuery)
            ->selectRaw('city as value, count(*) as count')
            ->groupBy('city')->orderBy('count', 'desc')
            ->get()
            ->map(fn($c) => ['label' => $c->value, 'value' => $c->value, 'count' => $c->count]);

        $etatAggregates = (clone $baseQuery)
            ->whereNotNull('etat')
            ->selectRaw('etat as value, count(*) as count')
            ->groupBy('etat')
            ->get()
            ->map(fn($e) => ['label' => $e->value, 'value' => $e->value, 'count' => $e->count]);

        // Commodités : on agrège depuis le JSON amenities
        $allAmenities = [
            'Climatisation',
            'Parking',
            'Sécurité 24/7',
            'Wi-Fi',
            'Eau courante',
            'Électricité permanente',
            'Gardiennage',
            'Groupe électrogène',
            'Balcon',
            'Jardin',
            'Cuisine équipée',
        ];
        $amenityAggregates = collect($allAmenities)->map(function ($a) use ($baseQuery) {
            $count = (clone $baseQuery)->where('amenities', 'like', '%' . $a . '%')->count();
            return ['label' => $a, 'value' => $a, 'count' => $count];
        })->filter(fn($a) => $a['count'] > 0)->values();

        return response()->json([
            'success'    => true,
            'data'       => $properties,
            'aggregates' => [
                'types'     => $typeAggregates,
                'cities'    => $cityAggregates,
                'etats'     => $etatAggregates,
                'amenities' => $amenityAggregates,
            ],
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:rent,sale',
            'price' => 'required|numeric',
            'location' => 'required|string',
            'city' => 'required|string',
            'description' => 'nullable|string',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'area' => 'nullable|numeric',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($request->title) . '-' . Str::random(6);
        $validated['status'] = 'pending'; // Default moderation status

        $property = Property::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('properties', 'public');
                $property->images()->create([
                    'path' => $path,
                    'is_primary' => $index === 0,
                    'order' => $index,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Propriété créée avec succès. En attente de modération.',
            'data' => $property->load('images'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $property = Property::with(['owner:id,name,email,avatar,phone', 'images'])
            ->findOrFail($id);

        $property->increment('views_count');

        // Normaliser toutes les images
        $allImages = $property->images->map(function ($img) {
            $path = $img->path;
            return [
                'id'         => $img->id,
                'url'        => str_starts_with($path, 'http') ? $path : asset('storage/' . $path),
                'is_primary' => $img->is_primary,
                'order'      => $img->order,
            ];
        })->sortBy('order')->values();

        // Image principale
        $primary = $allImages->firstWhere('is_primary', true) ?? $allImages->first();
        $property->image = $primary['url'] ?? 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800';
        $property->all_images = $allImages->pluck('url')->all();

        $user = auth('sanctum')->user();
        $favoriteIds = $user ? $user->favorites()->pluck('property_id')->toArray() : [];
        $property->is_favorite = in_array($property->id, $favoriteIds);

        // Biens similaires ...
        $similar = Property::with(['primaryImage'])
            ->where('status', 'active')
            ->where('id', '!=', $property->id)
            ->where(function ($q) use ($property) {
                $q->where('category', $property->category)
                    ->orWhere('city', $property->city);
            })
            ->inRandomOrder()
            ->limit(4)
            ->get()
            ->map(function ($p) use ($favoriteIds) {
                $pi   = $p->primaryImage;
                $path = $pi ? $pi->path : null;
                $p->image = $path
                    ? (str_starts_with($path, 'http') ? $path : asset('storage/' . $path))
                    : 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800';
                $p->rooms = $p->bedrooms ?? 0;
                $p->is_favorite = in_array($p->id, $favoriteIds);
                return $p;
            });

        return response()->json([
            'success'  => true,
            'data'     => $property,
            'similar'  => $similar,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $property = Property::findOrFail($id);

        if ($request->user()->id !== $property->user_id && $request->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $property->update($request->only([
            'title',
            'type',
            'price',
            'location',
            'city',
            'description',
            'bedrooms',
            'bathrooms',
            'area',
            'features'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Propriété mise à jour',
            'data' => $property,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $property = Property::findOrFail($id);

        if ($request->user()->id !== $property->user_id && $request->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Potential cleanup of images from storage
        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Propriété supprimée',
        ]);
    }
}
