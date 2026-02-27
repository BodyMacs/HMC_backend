<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'area' => 'decimal:2',
        'amenities' => 'array',
        'features' => 'array',
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

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order');
    }

    public function primaryImage()
    {
        return $this->hasOne(PropertyImage::class)->where('is_primary', true);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites', 'property_id', 'user_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }
}
