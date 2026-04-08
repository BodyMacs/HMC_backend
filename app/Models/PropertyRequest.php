<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'documents' => 'array',
        'price_estimate' => 'decimal:2',
        'visited_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'bailleur_confirmed_at' => 'datetime',
        'bailleur_declined_at' => 'datetime',
        'bailleur_suggested_at' => 'datetime',
    ];

    /** Bailleur who requested the publication */
    public function bailleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Agent HMC assigned to the request */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
