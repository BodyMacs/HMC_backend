<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePost;
use App\Models\ServicePostResponse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ServiceCategory::all();
        if ($categories->isEmpty()) {
            $this->call(ServiceCategorySeeder::class);
            $categories = ServiceCategory::all();
        }

        // 1. Defini 16 providers with real attributes
        $cities = ['Douala', 'Yaoundé', 'Kribi', 'Bafoussam', 'Garoua', 'Limbe'];
        $avatars = [
            '/images/avatar/avatar1.webp',
            '/images/avatar/avatar2.webp',
            '/images/avatar/avatar3.webp',
            '/images/img1.jpeg',
            '/images/img2.jpeg',
            '/images/img3.jpeg',
            '/images/img4.jpeg',
            '/images/img5.jpeg',
            '/images/img6.jpeg',
            '/images/tete1.jpeg',
        ];

        $specialties = [
            'Plombier Sanitaire',
            'Électricien Bâtiment',
            'Peintre Décorateur',
            'Technicien Froid',
            'Menuisier Aluminium',
            'Electronicien',
            'Mécanicien Automobile',
            'Informaticien Réseau',
            'Serrurier Coffre-fort',
            'Maçon Finisseur',
            'Platrier',
            'Carreleur Expert',
            'Jardinier Paysagiste',
            'Agent de Sécurité Électronique',
            'Soudeur Industriel',
            'Couturier Professionnel'
        ];

        $names = [
            'Jean-Paul Kamga',
            'Saliou Bello',
            'Moussa Ibrahim',
            'Christian Tchakounté',
            'Fabrice Ndumbe',
            'Erika Mbianda',
            'Hervé Kotto',
            'Samuel Wandji',
            'André Batum',
            'Gisèle Ngo Nouga',
            'Blaise Tsafack',
            'Raoul Nkoumou',
            'Yasmine Abena',
            'Eric Mvondo',
            'Didier Fotso',
            'Patrick Edouard'
        ];

        $providers = [];

        for ($i = 0; $i < 16; $i++) {
            $city = $cities[$i % count($cities)];
            $name = $names[$i];
            $specialty = $specialties[$i];
            $avatar = $avatars[$i % count($avatars)];
            $email = Str::slug($name) . '@prestataire.cm';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt('password'),
                    'role' => 'prestataire',
                    'roles' => ['prestataire'],
                    'city' => $city,
                    'status' => 'active',
                    'phone' => '2376' . rand(10000000, 99999999),
                    'avatar_url' => $avatar,
                    'bio' => "Professionnel certifié en {$specialty} basé à {$city}. Plus de 5 ans d'expérience dans le domaine avec des interventions de qualité garanties.",
                ]
            );
            $providers[] = $user;

            // Give each provider 2-3 services
            $randomCats = $categories->random(rand(2, 3));
            foreach ($randomCats as $cat) {
                Service::updateOrCreate(
                    ['provider_id' => $user->id, 'category_id' => $cat->id],
                    [
                        'title' => "Service de {$cat->name} Professionnel",
                        'description' => "Intervention rapide pour tout besoin en {$cat->name}. Qualité certifiée HMC.",
                        'base_price' => rand(5, 45) * 1000,
                        'status' => 'active',
                    ]
                );
            }
        }

        // 2. Create some job board posts (Needs)
        $clients = User::whereJsonContains('roles', 'locataire')->orWhere('role', 'locataire')->get();
        if ($clients->isEmpty()) {
            $clients = collect([User::first()]);
        }

        $jobTitles = [
            'Fuite d\'eau sous l\'évier',
            'Installation d\'un nouveau climatiseur',
            'Peinture complète salon 40m2',
            'Serrure bloquée porte principale',
            'Câblage réseau bureau à domicile',
            'Remise aux normes tableau électrique',
            'Débouchage canalisations cuisine',
            'Réparation toiture (tuiles)',
            'Installation caméra de surveillance',
            'Montage meuble TV',
            'Remplissage Gaz Clim auto',
            'Vernissage portail bois',
        ];

        foreach ($jobTitles as $index => $title) {
            $client = $clients->random();
            $cat = $categories->random();
            $city = $cities[array_rand($cities)];

            $post = ServicePost::create([
                'client_id' => $client->id,
                'category_id' => $cat->id,
                'title' => $title,
                'description' => "J'ai besoin de toute urgence d'un professionnel pour : {$title}. Le travail est à effectuer au quartier " . ['Akwa', 'Bonapriso', 'Bastos', 'Santa Barbara'][rand(0, 3)] . ".",
                'city' => $city,
                'neighborhood' => ['Akwa', 'Bonapriso', 'Bastos', 'Mairie'][rand(0, 3)],
                'min_budget' => rand(10, 20) * 1000,
                'max_budget' => rand(25, 80) * 1000,
                'urgency' => ['low', 'medium', 'high'][rand(0, 2)],
                'status' => 'open',
                'preferred_date' => now()->addDays(rand(1, 10)),
            ]);

            // Add some responses for some posts (simulating bidding)
            if ($index < 8) {
                $numResponses = rand(1, 3);
                $availableProviders = collect($providers);
                for ($j = 0; $j < $numResponses; $j++) {
                    $provider = $availableProviders->random();
                    ServicePostResponse::create([
                        'post_id' => $post->id,
                        'provider_id' => $provider->id,
                        'message' => "Bonjour, je suis disponible pour cette intervention. Devis estimatif après constatations.",
                        'proposed_price' => $post->max_budget - (rand(1, 5) * 1000),
                        'status' => 'pending',
                    ]);
                }
            }
        }
    }
}
