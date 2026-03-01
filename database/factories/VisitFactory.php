<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Visit>
 */
class VisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id'        => Property::factory(),
            'user_id'            => User::factory(),
            'agent_id'           => null,
            'scheduled_at'       => $this->faker->dateTimeBetween('+1 day', '+14 days'),
            'status'             => 'pending',
            'notes'              => $this->faker->optional()->sentence(),
            'visit_fee'          => 15000,
            'fee_payment_status' => 'pending',
            'fee_payment_method' => null,
            'confirmed_by_user'  => false,
            'confirmed_by_agent' => false,
            'visited_at'         => null,
        ];
    }

    /** Visite en attente de confirmation. */
    public function pending(): static
    {
        return $this->state(fn() => [
            'status'             => 'pending',
            'confirmed_by_user'  => false,
            'confirmed_by_agent' => false,
        ]);
    }

    /** Visite confirmée par l'agent (mais pas encore par l'user). */
    public function confirmedByAgent(User $agent): static
    {
        return $this->state(fn() => [
            'status'             => 'confirmed',
            'agent_id'           => $agent->id,
            'confirmed_by_agent' => true,
            'confirmed_by_user'  => false,
            'visited_at'         => now(),
        ]);
    }

    /** Visite confirmée par l'user (mais pas encore par l'agent). */
    public function confirmedByUser(): static
    {
        return $this->state(fn() => [
            'status'             => 'confirmed',
            'confirmed_by_user'  => true,
            'confirmed_by_agent' => false,
        ]);
    }

    /** Visite terminée — les deux parties ont confirmé. */
    public function completed(User $agent): static
    {
        return $this->state(fn() => [
            'status'             => 'completed',
            'agent_id'           => $agent->id,
            'confirmed_by_user'  => true,
            'confirmed_by_agent' => true,
            'visited_at'         => now()->subHours(2),
        ]);
    }

    /** Visite annulée. */
    public function cancelled(): static
    {
        return $this->state(fn() => ['status' => 'cancelled']);
    }

    /** Avec un agent assigné. */
    public function withAgent(User $agent): static
    {
        return $this->state(fn() => ['agent_id' => $agent->id]);
    }
}
