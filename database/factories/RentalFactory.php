<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental>
 */
class RentalFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $durationMonths = $this->faker->randomElement([6, 12, 24]);
        $endDate = (clone $startDate)->modify("+{$durationMonths} months");

        return [
            'tenant_id'      => User::factory(),
            'property_id'    => Property::factory(),
            'price'          => $this->faker->randomElement([50000, 80000, 100000, 150000]),
            'monthly_rent'   => null,
            'payment_status' => 'unpaid',
            'status'         => 'pending',
            'start_date'     => $startDate->format('Y-m-d'),
            'end_date'       => $endDate->format('Y-m-d'),
            'notes'          => $this->faker->optional()->sentence(),
        ];
    }

    /** Demande en attente de validation bailleur. */
    public function pending(): static
    {
        return $this->state(fn() => [
            'status'     => 'pending',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date'   => now()->addMonths(12)->addDays(5)->format('Y-m-d'),
        ]);
    }

    /** Location active (acceptée par le bailleur). */
    public function active(): static
    {
        return $this->state(fn() => [
            'status'     => 'active',
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date'   => now()->addMonths(11)->format('Y-m-d'),
        ]);
    }

    /** Location annulée. */
    public function cancelled(): static
    {
        return $this->state(fn() => [
            'status'     => 'cancelled',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date'   => now()->addMonths(12)->addDays(5)->format('Y-m-d'),
        ]);
    }

    /** Location terminée. */
    public function finished(): static
    {
        return $this->state(fn() => [
            'status'     => 'finished',
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date'   => now()->subDays(5)->format('Y-m-d'),
        ]);
    }
}
