<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_at'      => 'datetime',
        'visited_at'        => 'datetime',
        'confirmed_by_user'  => 'boolean',
        'confirmed_by_agent' => 'boolean',
        'visit_fee'          => 'decimal:2',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function visitor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function application()
    {
        return $this->hasOne(RentalApplication::class, 'visit_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * La visite est "complète" quand les deux parties ont confirmé.
     */
    public function isBothConfirmed(): bool
    {
        return $this->confirmed_by_user && $this->confirmed_by_agent;
    }

    /**
     * Marque la visite comme terminée si les deux parties ont confirmé.
     */
    public function checkAndComplete(): void
    {
        // La visite devient "completed" dès que les deux parties ont confirmé,
        // quel que soit le statut intermédiaire (pending ou confirmed).
        if ($this->isBothConfirmed() && in_array($this->status, ['pending', 'confirmed'], true)) {
            $this->update(['status' => 'completed']);
        }
    }
}
