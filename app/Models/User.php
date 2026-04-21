<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    protected static function booted(): void
    {
        static::creating(function (User $user) {
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
        'availabilities',
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
        'availabilities' => 'array',
    ];

    protected $appends = ['avatar_url'];

public function getAvatarUrlAttribute()
{
    // Cas 1: L'avatar est déjà une URL complète (https, http, data:image)
    if ($this->avatar && filter_var($this->avatar, FILTER_VALIDATE_URL)) {
        return $this->avatar;
    }
    
    // Cas 2: L'avatar est stocké localement (dans storage)
    if ($this->avatar) {
        return asset('storage/' . $this->avatar);
    }
    
    // Cas 3: Avatar par défaut selon le rôle
    if ($this->role === 'agent') {
        // Assign a consistent image based on ID
        $num = ($this->id % 4) + 1;
        return asset('storage/user_profil/agent' . $num . '.jpg');
    }
    
    // Cas 4: Avatar par défaut générique
    return asset('images/avatar/default.png');
}
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
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'user_id');
    }

    // ─── Relations Agent HMC ──────────────────────────────────────────────────

    /** Biens dont cet agent est responsable */
    public function managedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'agent_id');
    }

    /** Visites assignées à cet agent */
    public function assignedVisits(): HasMany
    {
        return $this->hasMany(Visit::class, 'agent_id');
    }

    /** Dossiers assignés à cet agent */
    public function assignedApplications(): HasMany
    {
        return $this->hasMany(RentalApplication::class, 'agent_id');
    }

    /** Locations dont cet agent est responsable */
    public function managedRentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'agent_id');
    }

    // ─── Relations Locataire ──────────────────────────────────────────────────

    /** Locations actives en tant que locataire */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'tenant_id');
    }

    /** Candidatures de location soumises */
    public function rentalApplications(): HasMany
    {
        return $this->hasMany(RentalApplication::class, 'user_id');
    }

    /** Visites programmées */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'user_id');
    }

    // ─── Relations génériques ─────────────────────────────────────────────────

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'favorites', 'user_id', 'property_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function formations(): BelongsToMany
    {
        return $this->belongsToMany(Formation::class, 'user_formations')
            ->withPivot('status', 'progress', 'completed_at')
            ->withTimestamps();
    }

    // ─── Relations Prestataire & Services ──────────────────────────────────────

    /** Services offerts par ce prestataire */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    /** Demandes de services postées par ce client */
    public function servicePosts(): HasMany
    {
        return $this->hasMany(ServicePost::class, 'client_id');
    }

    /** Offres (bids) soumises par ce prestataire sur des posts */
    public function servicePostResponses(): HasMany
    {
        return $this->hasMany(ServicePostResponse::class, 'provider_id');
    }
}
