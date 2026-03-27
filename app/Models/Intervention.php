<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Intervention extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function provider(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Service::class, 'id', 'id', 'service_id', 'provider_id');
    }
}
