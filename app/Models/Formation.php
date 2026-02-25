<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_formations')
            ->withPivot('status', 'progress', 'completed_at')
            ->withTimestamps();
    }
}
