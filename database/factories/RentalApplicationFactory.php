<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RentalApplication>
 */
class RentalApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id'               => Property::factory(),
            'user_id'                   => User::factory(),
            'visit_id'                  => null,
            'agent_id'                  => null,
            'situation_professionnelle' => $this->faker->randomElement(['cdi', 'cdd', 'independant', 'fonctionnaire', 'etudiant']),
            'revenus_mensuels'          => $this->faker->randomElement([75000, 100000, 150000, 200000, 250000]),
            'has_garant'                => $this->faker->boolean(30),
            'notes'                     => $this->faker->optional()->sentence(),
            'status'                    => 'pending',
            'rejection_reason'          => null,
            'reviewed_at'               => null,
            'reviewed_by'               => null,
            'documents'                 => null,
            'signed_by_applicant'       => false,
            'signed_at'                 => null,
        ];
    }

    /** Dossier en attente d'examen. */
    public function pending(): static
    {
        return $this->state(fn() => ['status' => 'pending']);
    }

    /** Dossier validé par l'agent. */
    public function validated(User $agent): static
    {
        return $this->state(fn() => [
            'status'      => 'validated',
            'agent_id'    => $agent->id,
            'reviewed_at' => now(),
            'reviewed_by' => $agent->id,
        ]);
    }

    /** Dossier rejeté par l'agent. */
    public function rejected(User $agent, string $reason = 'Revenus insuffisants.'): static
    {
        return $this->state(fn() => [
            'status'           => 'rejected',
            'agent_id'         => $agent->id,
            'reviewed_at'      => now(),
            'reviewed_by'      => $agent->id,
            'rejection_reason' => $reason,
        ]);
    }

    /** Avec un agent assigné. */
    public function withAgent(User $agent): static
    {
        return $this->state(fn() => ['agent_id' => $agent->id]);
    }

    /** Avec une visite liée. */
    public function forVisit(Visit $visit): static
    {
        return $this->state(fn() => [
            'visit_id'    => $visit->id,
            'property_id' => $visit->property_id,
            'user_id'     => $visit->user_id,
            'agent_id'    => $visit->agent_id,
        ]);
    }
}
