<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePostResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'provider_id',
        'message',
        'proposed_price',
        'status',
    ];

    protected $casts = [
        'proposed_price' => 'decimal:2',
    ];

    /** The job board post this response belongs to */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ServicePost::class, 'post_id');
    }

    /** The provider responding to the post */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
