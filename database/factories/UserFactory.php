<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => 'client',
            'roles'             => ['client'],
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Agent HMC */
    public function agent(): static
    {
        return $this->state(fn() => [
            'role'  => 'agent',
            'roles' => ['agent'],
        ]);
    }

    /** Propriétaire bailleur */
    public function bailleur(): static
    {
        return $this->state(fn() => [
            'role'  => 'bailleur',
            'roles' => ['bailleur'],
        ]);
    }

    /** Client (sans rôle locataire) */
    public function client(): static
    {
        return $this->state(fn() => [
            'role'  => 'client',
            'roles' => ['client'],
        ]);
    }

    /** Locataire (rôle attribué après location confirmée) */
    public function tenant(): static
    {
        return $this->state(fn() => [
            'role'  => 'locataire',
            'roles' => ['locataire'],
        ]);
    }
}
