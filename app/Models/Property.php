<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Property extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'price'     => 'decimal:2',
        'area'      => 'integer',
        'amenities' => 'array',
        'features'  => 'array',
    ];

    /**
     * Stocker les commodités en JSON lisible (sans \uXXXX) pour les recherches LIKE
     */
    public function setAmenitiesAttribute($value): void
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        $this->attributes['amenities'] = json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    public function getAmenitiesAttribute($value): array
    {
        return is_string($value) ? (json_decode($value, true) ?? []) : ($value ?? []);
    }

    public function setFeaturesAttribute($value): void
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        $this->attributes['features'] = json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    public function getFeaturesAttribute($value): array
    {
        return is_string($value) ? (json_decode($value, true) ?? []) : ($value ?? []);
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    /** Bailleur propriétaire du bien */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Agent HMC qui gère ce bien physiquement */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)->where('is_primary', true);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites', 'property_id', 'user_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RentalApplication::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PropertyReview::class)->where('status', 'approved');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return in_array($this->status, ['active']);
    }

    public function isRented(): bool
    {
        return $this->status === 'rented';
    }
}
