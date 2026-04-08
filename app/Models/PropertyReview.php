<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyReview extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'rating'             => 'integer',
        'is_verified_tenant' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'name', 'avatar']);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
