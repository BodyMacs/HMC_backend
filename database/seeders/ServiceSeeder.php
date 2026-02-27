<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have a provider
        $provider = User::where('role', 'prestataire')->first();

        if (! $provider) {
            $provider = User::create([
                'name' => 'Prestataire Pro',
                'email' => 'pro@home.cm',
                'password' => bcrypt('password'),
                'role' => 'prestataire',
                'status' => 'active',
            ]);
        }

        $categories = ServiceCategory::all();

        if ($categories->isEmpty()) {
            return;
        }

        $services = [
            [
                'title' => 'Réparation de Fuites',
                'description' => 'Service rapide pour toutes fuites d\'eau : robinets, tuyaux, etc.',
                'base_price' => 5000,
                'category_slug' => 'plomberie',
            ],
            [
                'title' => 'Installation Électrique Complète',
                'description' => 'Installation sécurisée pour nouvelles constructions ou rénovations.',
                'base_price' => 50000,
                'category_slug' => 'electricite',
            ],
            [
                'title' => 'Peinture Murale',
                'description' => 'Peinture de haute qualité pour intérieur et extérieur.',
                'base_price' => 1500, // au m2 par exemple
                'category_slug' => 'peinture',
            ],
            [
                'title' => 'Nettoyage de Printemps',
                'description' => 'Nettoyage complet de votre domicile du sol au plafond.',
                'base_price' => 10000,
                'category_slug' => 'nettoyage',
            ],
            [
                'title' => 'Installation Climatiseur',
                'description' => 'Pose et mise en service de votre système de climatisation.',
                'base_price' => 15000,
                'category_slug' => 'climatisation',
            ],
            [
                'title' => 'Menuiserie sur Mesure',
                'description' => 'Création de meubles et placards sur mesure.',
                'base_price' => 25000,
                'category_slug' => 'menuiserie',
            ],
        ];

        foreach ($services as $serviceData) {
            $category = $categories->where('slug', $serviceData['category_slug'])->first();

            // Fallback if slug not found
            if (! $category) {
                $category = $categories->first();
            }

            Service::create([
                'provider_id' => $provider->id,
                'category_id' => $category->id,
                'title' => $serviceData['title'],
                'description' => $serviceData['description'],
                'base_price' => $serviceData['base_price'],
                'status' => 'active',
            ]);
        }
    }
}
