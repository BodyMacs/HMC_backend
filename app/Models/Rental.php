<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrat de location final.
 *
 * N'est créé QU'APRÈS :
 *   - Dossier validé par l'agent (RentalApplication::status = validated)
 *   - Paiement initial confirmé par l'agent
 *
 * C'est la création de ce record + confirmation paiement
 * qui déclenche l'attribution du rôle "locataire".
 */
class Rental extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date'           => 'date',
        'end_date'             => 'date',
        'price'                => 'decimal:2',
        'caution_amount'       => 'decimal:2',
        'advance_amount'       => 'decimal:2',
        'payment_confirmed_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /** Locataire officiel (rôle attribué après confirmation du paiement) */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /** Agent HMC responsable de ce dossier */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** Dossier de candidature qui a abouti à ce contrat */
    public function application()
    {
        return $this->belongsTo(RentalApplication::class, 'application_id');
    }

    /** Agent qui a confirmé la réception du paiement initial */
    public function paymentConfirmedBy()
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    /** Paiements liés à cette location */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaymentConfirmed(): bool
    {
        return $this->payment_phase_status === 'confirmed';
    }
}
