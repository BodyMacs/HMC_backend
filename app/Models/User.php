<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    protected static function booted()
    {
        static::creating(function ($user) {
            if ($user->role && empty($user->roles)) {
                $user->roles = [$user->role];
            }
        });
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'roles',
        'avatar',
        'city',
        'bio',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'roles' => 'array',
    ];

    /**
     * Check if user has a specific role in their roles list.
     */
    public function hasRole(string $role): bool
    {
        return is_array($this->roles) && in_array($role, $this->roles);
    }

    /**
     * Add a role to the user's list of roles.
     */
    public function addRole(string $role): void
    {
        $currentRoles = $this->roles ?? [];
        if (!in_array($role, $currentRoles)) {
            $currentRoles[] = $role;
            $this->roles = $currentRoles;
            $this->save();
        }
    }

    /**
     * Switch the current active role.
     */
    public function switchRole(string $role): bool
    {
        if ($this->hasRole($role)) {
            $this->role = $role;
            return $this->save();
        }

        return false;
    }

    // ─── Relations Bailleur / Propriétaire ────────────────────────────────────

    /** Biens dont cet utilisateur est le propriétaire (bailleur) */
    public function properties()
    {
        return $this->hasMany(Property::class, 'user_id');
    }

    // ─── Relations Agent HMC ──────────────────────────────────────────────────

    /** Biens dont cet agent est responsable */
    public function managedProperties()
    {
        return $this->hasMany(Property::class, 'agent_id');
    }

    /** Visites assignées à cet agent */
    public function assignedVisits()
    {
        return $this->hasMany(Visit::class, 'agent_id');
    }

    /** Dossiers assignés à cet agent */
    public function assignedApplications()
    {
        return $this->hasMany(RentalApplication::class, 'agent_id');
    }

    /** Locations dont cet agent est responsable */
    public function managedRentals()
    {
        return $this->hasMany(Rental::class, 'agent_id');
    }

    // ─── Relations Locataire ──────────────────────────────────────────────────

    /** Locations actives en tant que locataire */
    public function rentals()
    {
        return $this->hasMany(Rental::class, 'tenant_id');
    }

    /** Candidatures de location soumises */
    public function rentalApplications()
    {
        return $this->hasMany(RentalApplication::class, 'user_id');
    }

    /** Visites programmées */
    public function visits()
    {
        return $this->hasMany(Visit::class, 'user_id');
    }

    // ─── Relations génériques ─────────────────────────────────────────────────

    public function favorites()
    {
        return $this->belongsToMany(Property::class, 'favorites', 'user_id', 'property_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function formations()
    {
        return $this->belongsToMany(Formation::class, 'user_formations')
            ->withPivot('status', 'progress', 'completed_at')
            ->withTimestamps();
    }
}
