<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'documents' => 'array',
        'price_estimate' => 'decimal:2',
        'visited_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /** Bailleur who requested the publication */
    public function bailleur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Agent HMC assigned to the request */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
