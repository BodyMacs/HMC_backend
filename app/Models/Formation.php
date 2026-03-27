<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Formation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'badge',
        'price',
        'modules',
        'status',
    ];

    protected $casts = [
        'modules' => 'array',
        'price' => 'float',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_formations')
            ->withPivot('status', 'progress', 'completed_at')
            ->withTimestamps();
    }
}
