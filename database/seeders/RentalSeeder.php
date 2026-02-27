<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Intervention;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RentalSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = User::where('email', 'locataire@home.cm')->first();
        if (! $tenant) {
            return;
        }

        // 1. Favoris
        $properties = Property::inRandomOrder()->take(5)->get();
        foreach ($properties as $prop) {
            Favorite::firstOrCreate([
                'user_id' => $tenant->id,
                'property_id' => $prop->id,
            ]);
        }

        // 2. Locations Active & Passée
        $allProps = Property::where('status', 'active')->get();
        if ($allProps->count() < 2) {
            return;
        }

        // Location Active
        $activeProp = $allProps[0];
        $rentalActive = Rental::create([
            'property_id' => $activeProp->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(9),
            'price' => $activeProp->price,
            'status' => 'active',
        ]);
        $activeProp->update(['status' => 'rented']);

        // Location Finie
        $pastProp = $allProps[1];
        Rental::create([
            'property_id' => $pastProp->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonths(6),
            'price' => $pastProp->price,
            'status' => 'finished',
        ]);

        // 3. Transactions (Loyer)
        for ($i = 0; $i < 3; $i++) {
            Transaction::create([
                'user_id' => $tenant->id,
                'reference' => 'PAY-'.strtoupper(Str::random(10)),
                'type' => 'payment',
                'amount' => $activeProp->price,
                'currency' => 'XAF',
                'status' => 'successful',
                'payment_method' => 'momo',
                'metadata' => [
                    'rental_id' => $rentalActive->id,
                    'month' => now()->subMonths($i)->format('F Y'),
                ],
                'created_at' => now()->subMonths($i),
            ]);
        }

        // 4. Interventions
        $service = Service::first();
        if ($service) {
            Intervention::create([
                'service_id' => $service->id,
                'requester_id' => $tenant->id,
                'scheduled_at' => now()->addDays(2),
                'status' => 'pending',
                'notes' => 'Problème de plomberie dans la cuisine, robinet qui fuit.',
            ]);

            Intervention::create([
                'service_id' => $service->id,
                'requester_id' => $tenant->id,
                'scheduled_at' => now()->subDays(15),
                'status' => 'completed',
                'notes' => 'Réparation climatisation salon.',
                'completed_at' => now()->subDays(15),
                'rating' => 5,
                'review' => 'Excellent travail, prestataire ponctuel.',
            ]);
        }
    }
}
