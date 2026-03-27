<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'detail',
        'icon',
        'icon_bg',
        'icon_color',
        'action_label',
        'action_link',
        'reference_type',
        'reference_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ── Static helper — Création rapide ───────────────────────────────────────

    public static function send(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): self {
        return static::create([
            'user_id'        => $userId,
            'type'           => $type,
            'title'          => $title,
            'message'        => $message,
            'detail'         => $options['detail'] ?? null,
            'icon'           => $options['icon'] ?? static::defaultIcon($type),
            'icon_bg'        => $options['icon_bg'] ?? static::defaultBg($type),
            'icon_color'     => $options['icon_color'] ?? static::defaultColor($type),
            'action_label'   => $options['action_label'] ?? null,
            'action_link'    => $options['action_link'] ?? null,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id'   => $options['reference_id'] ?? null,
        ]);
    }

    private static function defaultIcon(string $type): string
    {
        return match ($type) {
            'visit'       => 'calendar-check',
            'application' => 'file-invoice',
            'payment'     => 'credit-card',
            'message'     => 'comment-dots',
            'alert'       => 'heart',
            'rental'      => 'key',
            'system'      => 'shield-alt',
            default       => 'bell',
        };
    }

    private static function defaultBg(string $type): string
    {
        return match ($type) {
            'visit'       => 'bg-green-100',
            'application' => 'bg-orange-100',
            'payment'     => 'bg-emerald-100',
            'message'     => 'bg-blue-100',
            'alert'       => 'bg-red-100',
            'rental'      => 'bg-indigo-100',
            'system'      => 'bg-purple-100',
            default       => 'bg-gray-100',
        };
    }

    private static function defaultColor(string $type): string
    {
        return match ($type) {
            'visit'       => 'text-green-600',
            'application' => 'text-orange-600',
            'payment'     => 'text-emerald-600',
            'message'     => 'text-blue-600',
            'alert'       => 'text-red-500',
            'rental'      => 'text-indigo-600',
            'system'      => 'text-purple-600',
            default       => 'text-gray-600',
        };
    }
}
