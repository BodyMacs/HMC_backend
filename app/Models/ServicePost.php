<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServicePost extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'category_id',
        'title',
        'description',
        'city',
        'neighborhood',
        'min_budget',
        'max_budget',
        'urgency',
        'status',
        'preferred_date',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
        'preferred_date' => 'datetime',
        'min_budget' => 'decimal:2',
        'max_budget' => 'decimal:2',
    ];

    /** Client who created the request */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** Category of the service */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /** Responses from providers */
    public function responses(): HasMany
    {
        return $this->hasMany(ServicePostResponse::class, 'post_id');
    }

    /** Conversation created from this post (if accepted) */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'service_post_id');
    }
}
