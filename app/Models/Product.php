<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'description',
        'price',
        'old_price',
        'category',
        'image',
        'location',
        'is_featured',
        'is_new',
        'rating',
        'reviews_count',
        'badge',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
