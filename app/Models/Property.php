<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'price'     => 'decimal:2',
        'area'      => 'decimal:2',
    ];

    /**
     * Stocker les commodités en JSON lisible (sans \uXXXX) pour les recherches LIKE
     */
    public function setAmenitiesAttribute($value): void
    {
        $this->attributes['amenities'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    public function getAmenitiesAttribute($value): array
    {
        return $value ? json_decode($value, true) ?? [] : [];
    }

    public function setFeaturesAttribute($value): void
    {
        $this->attributes['features'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    public function getFeaturesAttribute($value): array
    {
        return $value ? json_decode($value, true) ?? [] : [];
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
