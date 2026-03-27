<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyReview;
use App\Models\RentalApplication;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Property::with(['owner:id,name,avatar', 'primaryImage', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount(['reviews', 'favorites'])
            ->where('status', 'active');

        // Filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request): void {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('location', 'like', '%' . $request->search . '%')
                    ->orWhere('city', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }
        // Types multiples (CSV) ou unique
        if ($request->filled('types')) {
            $types = array_filter(array_map('trim', explode(',', $request->types)));
            if (! empty($types)) {
                $query->whereIn('category', $types);
            }
        } elseif ($request->filled('type')) {
            $query->where('category', $request->type);
        }
        // Villes multiples (CSV) ou unique
        if ($request->filled('cities')) {
            $citiesArr = array_filter(array_map('trim', explode(',', $request->cities)));
            if (! empty($citiesArr)) {
                $query->where(function ($q) use ($citiesArr): void {
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
            if (! empty($etats)) {
                $query->whereIn('etat', $etats);
            }
        } elseif ($request->filled('etat')) {
            $query->where('etat', $request->etat);
        }
        // Filtre commodités (JSON contains) — OR logique : au moins une
        if ($request->filled('amenities')) {
            $amens = array_filter(array_map('trim', explode(',', $request->amenities)));
            if (! empty($amens)) {
                $query->where(function ($q) use ($amens): void {
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

        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();
        $favoriteIds = $user ? $user->favorites()->pluck('property_id')->toArray() : [];

        // Procédures locatives en cours par l'user pour chaque bien
        $activeVisits       = $user ? Visit::where('user_id', $user->id)->whereIn('status', ['pending', 'confirmed', 'completed'])->get()->keyBy('property_id') : collect();
        $activeApplications = $user ? RentalApplication::where('user_id', $user->id)->whereNotIn('status', ['rejected'])->get()->keyBy('property_id') : collect();

        // Normalize image URL for each property
        $properties->getCollection()->transform(function ($p) use ($favoriteIds, $activeVisits, $activeApplications) {
            $primaryImage = $p->primaryImage;
            if ($primaryImage) {
                $path = $primaryImage->path;
                $p->image = str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
            } else {
                $p->image = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800';
            }
            $p->rooms        = $p->bedrooms ?? 0;
            $p->avg_rating   = round((float) ($p->reviews_avg_rating ?? 0), 1);
            $p->review_count = (int) ($p->reviews_count ?? 0);
            $p->favorites_count = (int) ($p->favorites_count ?? 0);
            $p->shares_count = (int) ($p->shares_count ?? 0);
            $p->all_images   = $p->images->map(fn($img) => $img->path)->toArray();
            $p->is_favorite  = in_array($p->id, $favoriteIds);
            $p->my_rental_process = self::buildRentalProcess(
                $activeVisits->get($p->id),
                $activeApplications->get($p->id),
            );

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
            'success' => true,
            'data' => $properties,
            'aggregates' => [
                'types' => $typeAggregates,
                'cities' => $cityAggregates,
                'etats' => $etatAggregates,
                'amenities' => $amenityAggregates,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:rent,sale',
            'category' => 'required|string',
            'price' => 'required|numeric',
            'location' => 'required|string',
            'city' => 'required|string',
            'description' => 'nullable|string',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'area' => 'nullable|numeric',
            'etat' => 'nullable|string',
            'amenities' => 'nullable', // Can be CSV string or array
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $user = $request->user();
        $validated['user_id'] = $user->id;
        $validated['slug'] = Str::slug($request->title) . '-' . Str::random(6);
        $validated['status'] = 'pending'; // Default moderation status

        // Fulfilling: "si un locataire decide de poster un bien on l'attribut automatiquement le role bailleur"
        if (!$user->hasRole('bailleur')) {
            $user->addRole('bailleur');
        }

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
    public function show($identifier): JsonResponse
    {
        $property = Property::with(['owner:id,name,email,avatar,phone', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount(['reviews', 'favorites'])
            ->where(function ($query) use ($identifier) {
                if (is_numeric($identifier)) {
                    $query->where('id', $identifier);
                } else {
                    $query->where('slug', $identifier);
                }
            })
            ->firstOrFail();

        $property->increment('views_count');

        // Normaliser toutes les images
        $allImages = $property->images->map(function ($img) {
            $path = $img->path;

            return [
                'id' => $img->id,
                'url' => str_starts_with($path, 'http') ? $path : asset('storage/' . $path),
                'is_primary' => $img->is_primary,
                'order' => $img->order,
            ];
        })->sortBy('order')->values();

        // Image principale
        $primary = $allImages->firstWhere('is_primary', true) ?? $allImages->first();
        $property->image = $primary['url'] ?? 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800';
        $property->all_images = $allImages->pluck('url')->all();

        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();
        $favoriteIds = $user ? $user->favorites()->pluck('property_id')->toArray() : [];
        $property->is_favorite = in_array($property->id, $favoriteIds);

        // Procédure locative en cours pour CE bien
        $activeVisit = $user
            ? Visit::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->latest()->first()
            : null;
        $activeApplication = $user
            ? RentalApplication::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->whereNotIn('status', ['rejected'])
            ->latest()->first()
            : null;

        $property->my_rental_process = self::buildRentalProcess($activeVisit, $activeApplication);

        // Agrégats d'avis
        $reviewStats = \App\Models\PropertyReview::where('property_id', $property->id)
            ->where('status', 'approved')
            ->selectRaw('
                COUNT(*) as total,
                ROUND(AVG(rating), 1) as average,
                SUM(rating = 5) as five,
                SUM(rating = 4) as four,
                SUM(rating = 3) as three,
                SUM(rating = 2) as two,
                SUM(rating = 1) as one
            ')
            ->first();

        $property->review_stats = $reviewStats;

        // Biens similaires ...
        $similar = Property::with(['primaryImage'])
            ->withCount(['reviews', 'favorites'])
            ->where('status', 'active')
            ->where('id', '!=', $property->id)
            ->where(function ($q) use ($property): void {
                $q->where('category', $property->category)
                    ->orWhere('city', $property->city);
            })
            ->inRandomOrder()
            ->limit(4)
            ->get()
            ->map(function ($p) use ($favoriteIds) {
                $pi = $p->primaryImage;
                $path = $pi ? $pi->path : null;
                $p->image = $path
                    ? (str_starts_with($path, 'http') ? $path : asset('storage/' . $path))
                    : 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800';
                $p->rooms = $p->bedrooms ?? 0;
                $p->favorites_count = (int) ($p->favorites_count ?? 0);
                $p->review_count = (int) ($p->reviews_count ?? 0);
                $p->shares_count = (int) ($p->shares_count ?? 0);
                $p->is_favorite = in_array($p->id, $favoriteIds);

                return $p;
            });

        return response()->json([
            'success' => true,
            'data'    => $property,
            'similar' => $similar,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        if ($request->user()->id !== $property->user_id && $request->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $property->update($request->only([
            'title',
            'type',
            'category',
            'price',
            'location',
            'city',
            'description',
            'bedrooms',
            'bathrooms',
            'area',
            'etat',
            'amenities',
            'features',
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
    public function destroy(Request $request, $id): JsonResponse
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

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Construit le résumé de procédure locative pour un user sur un bien donné.
     *
     * Retourne null si aucune procédure en cours.
     * Sinon :
     *   step    : 'visit' | 'dossier' | 'payment' | 'active'
     *   label   : libellé lisible
     *   percent : 0-100 pour la barre de progression
     *   visit   : objet visite ou null
     *   application : objet dossier ou null
     */
    public static function buildRentalProcess(?object $visit, ?object $application): ?array
    {
        if (! $visit && ! $application) {
            return null;
        }

        // Étape active basée sur ce qu'on a trouvé
        if ($application) {
            if ($application->status === 'validated') {
                return [
                    'step'        => 'payment',
                    'label'       => 'En attente de paiement',
                    'percent'     => 75,
                    'visit'       => $visit,
                    'application' => $application,
                ];
            }

            return [
                'step'        => 'dossier',
                'label'       => 'Dossier en examen',
                'percent'     => 50,
                'visit'       => $visit,
                'application' => $application,
            ];
        }

        // Visite seule
        $step = match ($visit->status ?? '') {
            'pending'   => ['step' => 'visit', 'label' => 'Visite programmée',  'percent' => 15],
            'confirmed' => ['step' => 'visit', 'label' => 'Visite confirmée',   'percent' => 30],
            'completed' => ['step' => 'dossier', 'label' => 'Visite terminée — dossier à soumettre', 'percent' => 40],
            default     => ['step' => 'visit', 'label' => 'Visite en cours',    'percent' => 20],
        };

        return array_merge($step, ['visit' => $visit, 'application' => null]);
    }

    /**
     * Increment share count for a property
     */
    public function incrementShare($id): JsonResponse
    {
        $property = Property::findOrFail($id);
        $property->increment('shares_count');

        return response()->json([
            'success' => true,
            'shares_count' => $property->shares_count,
        ]);
    }
}
