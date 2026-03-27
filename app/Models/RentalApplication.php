<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Dossier de candidature locative.
 *
 * Cycle de vie :
 *   1. Créé après confirmation mutuelle de la visite
 *   2. Validé ou rejeté par l'agent HMC
 *   3. Si validé → création du Rental (contrat final) + paiement initial
 */
class RentalApplication extends Model
{
    use HasFactory;

    protected $table = 'rental_applications';

    protected $guarded = ['id'];

    protected $casts = [
        'documents'           => 'array',
        'has_garant'          => 'boolean',
        'signed_by_applicant' => 'boolean',
        'signed_at'           => 'datetime',
        'reviewed_at'         => 'datetime',
        'revenus_mensuels'    => 'decimal:2',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** Futur locataire qui a soumis le dossier */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Visite qui a déclenché cette candidature */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /** Agent HMC responsable */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** Qui a examiné le dossier */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Contrat de location final (créé après validation + paiement) */
    public function rental(): HasOne
    {
        return $this->hasOne(Rental::class, 'application_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
