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

        // ─── PRESTATAIRES RÉALISTES (16) ───────────────────────────────────────
        // 'exp' = years ago when the provider created their first service (for experience_years calculation)
        $providers = [
            ['name' => 'Jean-Paul Kamga Talla',    'city' => 'Douala',    'specialty' => 'Plombier Sanitaire Certifié',         'cat' => 'Plomberie',      'price' => 15000, 'avatar' => 'https://i.pravatar.cc/150?img=5',  'exp' => 7],
            ['name' => 'Saliou Bello Hamadou',     'city' => 'Yaoundé',   'specialty' => 'Électricien Bâtiment & Industriel',   'cat' => 'Électricité',    'price' => 20000, 'avatar' => 'https://i.pravatar.cc/150?img=6',  'exp' => 5],
            ['name' => 'Moussa Ibrahim Waziri',    'city' => 'Garoua',    'specialty' => 'Technicien Froid & Clim',              'cat' => 'Climatisation',  'price' => 25000, 'avatar' => 'https://i.pravatar.cc/150?img=7',  'exp' => 4],
            ['name' => 'Christian Tchakounté',     'city' => 'Douala',    'specialty' => 'Peintre Décorateur Intérieur',         'cat' => 'Peinture',       'price' => 18000, 'avatar' => 'https://i.pravatar.cc/150?img=8',  'exp' => 6],
            ['name' => 'Fabrice Ndumbe Epée',      'city' => 'Douala',    'specialty' => 'Menuisier Aluminium & PVC',            'cat' => 'Menuiserie',     'price' => 22000, 'avatar' => 'https://i.pravatar.cc/150?img=9',  'exp' => 3],
            ['name' => 'Erika Mbianda Nkolo',      'city' => 'Yaoundé',   'specialty' => 'Agent de Nettoyage Professionnel',     'cat' => 'Nettoyage',      'price' => 10000, 'avatar' => 'https://i.pravatar.cc/150?img=26', 'exp' => 2],
            ['name' => 'Hervé Kotto Bonono',       'city' => 'Douala',    'specialty' => 'Maçon Finisseur & Carreleur',          'cat' => 'Maçonnerie',     'price' => 20000, 'avatar' => 'https://i.pravatar.cc/150?img=13', 'exp' => 5],
            ['name' => 'Samuel Wandji Ngafou',     'city' => 'Bafoussam', 'specialty' => 'Électricien Réseau & Domotique',       'cat' => 'Électricité',    'price' => 25000, 'avatar' => 'https://i.pravatar.cc/150?img=15', 'exp' => 4],
            ['name' => 'André Batum Ngapout',      'city' => 'Douala',    'specialty' => 'Plombier Sanitaire & Forage',          'cat' => 'Plomberie',      'price' => 18000, 'avatar' => 'https://i.pravatar.cc/150?img=17', 'exp' => 3],
            ['name' => 'Gisèle Ngo Nouga',         'city' => 'Yaoundé',   'specialty' => 'Paysagiste & Jardinière Certifiée',    'cat' => 'Jardinage',      'price' => 12000, 'avatar' => 'https://i.pravatar.cc/150?img=31', 'exp' => 1],
            ['name' => 'Blaise Tsafack Nguemo',    'city' => 'Bafoussam', 'specialty' => 'Technicien Clim & Réfrigération',      'cat' => 'Climatisation',  'price' => 30000, 'avatar' => 'https://i.pravatar.cc/150?img=18', 'exp' => 6],
            ['name' => 'Raoul Nkoumou Biyogue',    'city' => 'Douala',    'specialty' => 'Peintre Décorateur Façades',           'cat' => 'Peinture',       'price' => 22000, 'avatar' => 'https://i.pravatar.cc/150?img=19', 'exp' => 2],
            ['name' => 'Yasmine Abena Kollo',      'city' => 'Yaoundé',   'specialty' => 'Femme de Ménage & Organisation',       'cat' => 'Nettoyage',      'price' => 8000,  'avatar' => 'https://i.pravatar.cc/150?img=32', 'exp' => 3],
            ['name' => 'Eric Mvondo Ateba',        'city' => 'Douala',    'specialty' => 'Maçon Spécialiste Piscines',           'cat' => 'Maçonnerie',     'price' => 35000, 'avatar' => 'https://i.pravatar.cc/150?img=20', 'exp' => 7],
            ['name' => 'Didier Fotso Taboh',       'city' => 'Bafoussam', 'specialty' => 'Menuisier Bois Massif & Sculpture',    'cat' => 'Menuiserie',     'price' => 28000, 'avatar' => 'https://i.pravatar.cc/150?img=22', 'exp' => 5],
            ['name' => 'Patrick Edouard Nkoa',     'city' => 'Douala',    'specialty' => 'Électricien Solaire & Photovoltaïque', 'cat' => 'Électricité',    'price' => 40000, 'avatar' => 'https://i.pravatar.cc/150?img=23', 'exp' => 4],
        ];

        $providerUsers = [];

        foreach ($providers as $i => $data) {
            $email = Str::slug($data['name']) . '@prestataire.cm';
            $cat   = $categories->firstWhere('name', $data['cat']) ?? $categories->first();

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $data['name'],
                    'password'          => bcrypt('password'),
                    'role'              => 'prestataire',
                    'roles'             => ['prestataire'],
                    'city'              => $data['city'],
                    'status'            => 'active',
                    'email_verified_at' => now(),
                    'phone'             => '+2376' . str_pad((string) ($i * 1117 + 99000000), 8, '0', STR_PAD_LEFT),
                    'avatar'            => $data['avatar'],
                    'bio'               => "Professionnel certifié en {$data['specialty']} basé à {$data['city']}. Plus de 5 ans d'expérience. Interventions rapides, résultats garantis.",
                ]
            );
            $providerUsers[] = $user;

            // Service principal lié à la spécialité — backdaté pour expérience réelle
            $firstServiceDate = now()->subYears($data['exp'])->subDays(rand(0, 90));
            $firstService = Service::updateOrCreate(
                ['provider_id' => $user->id, 'category_id' => $cat->id],
                [
                    'title'       => $data['specialty'],
                    'description' => "Expert en {$data['specialty']} à {$data['city']}. Intervention sous 24h pour toute urgence sur vos biens immobiliers. Devis gratuit.",
                    'base_price'  => $data['price'],
                    'status'      => 'active',
                ]
            );
            // Mise à jour de la date de création pour simuler l'ancienneté
            $firstService->created_at = $firstServiceDate;
            $firstService->updated_at = now()->subDays(rand(1, 30));
            $firstService->saveQuietly();

            // 1 service secondaire dans une catégorie connexe
            $secondaryCat = $categories->where('id', '!=', $cat->id)->random();
            Service::updateOrCreate(
                ['provider_id' => $user->id, 'category_id' => $secondaryCat->id],
                [
                    'title'       => "Assistance {$secondaryCat->name} — {$data['city']}",
                    'description' => "Service secondaire : {$secondaryCat->name} disponible en complément de mon activité principale.",
                    'base_price'  => round($data['price'] * 0.8 / 1000) * 1000,
                    'status'      => 'active',
                ]
            );
        }

        // ─── DEMANDES DE SERVICE (SERVICE POSTS) ───────────────────────────────
        $clientUsers = User::whereIn('role', ['locataire', 'client'])->get();
        if ($clientUsers->isEmpty()) {
            $clientUsers = collect([User::first()]);
        }

        $jobPosts = [
            [
                'title'       => 'Fuite d\'eau sous l\'évier de la cuisine',
                'description' => 'Robinet de cuisine qui fuit depuis 3 jours. Beaucoup de gaspillage, j\'ai besoin d\'un plombier urgent à Bonapriso ce weekend.',
                'city'        => 'Douala',
                'neighborhood'=> 'Bonapriso',
                'cat'         => 'Plomberie',
                'min_budget'  => 10000,
                'max_budget'  => 25000,
                'urgency'     => 'high',
            ],
            [
                'title'       => 'Installation d\'un nouveau climatiseur Split 12000 BTU',
                'description' => 'Je viens d\'acheter un clim Midea 12000 BTU sur HMC Market. Besoin d\'un technicien pour l\'installation et la mise en service dans ma chambre.',
                'city'        => 'Yaoundé',
                'neighborhood'=> 'Bastos',
                'cat'         => 'Climatisation',
                'min_budget'  => 20000,
                'max_budget'  => 40000,
                'urgency'     => 'medium',
            ],
            [
                'title'       => 'Peinture complète salon + 2 chambres',
                'description' => 'Besoin de repeindre un appartement F3 de 90m² à Makepe. Peinture blanche pour le salon et couleur au choix pour les chambres. Inclure le matériel.',
                'city'        => 'Douala',
                'neighborhood'=> 'Makepe',
                'cat'         => 'Peinture',
                'min_budget'  => 80000,
                'max_budget'  => 150000,
                'urgency'     => 'low',
            ],
            [
                'title'       => 'Serrure bloquée — porte principale',
                'description' => 'La serrure de ma porte d\'entrée est bloquée depuis ce matin. Je suis coincé dehors ! Urgent, Bastos Yaoundé.',
                'city'        => 'Yaoundé',
                'neighborhood'=> 'Bastos',
                'cat'         => 'Menuiserie',
                'min_budget'  => 15000,
                'max_budget'  => 35000,
                'urgency'     => 'high',
            ],
            [
                'title'       => 'Tableau électrique à remettre aux normes',
                'description' => 'Mon tableau électrique est vieux et présente des risques. Besoin d\'un électricien certifié pour remise aux normes complète et ajout de disjoncteurs.',
                'city'        => 'Douala',
                'neighborhood'=> 'Bonanjo',
                'cat'         => 'Électricité',
                'min_budget'  => 50000,
                'max_budget'  => 120000,
                'urgency'     => 'medium',
            ],
            [
                'title'       => 'Débouchage canalisations (WC + cuisine)',
                'description' => 'WC bouché depuis ce matin, impossible d\'utiliser les toilettes. Intervention urgente nécessaire à Omnisport.',
                'city'        => 'Yaoundé',
                'neighborhood'=> 'Omnisports',
                'cat'         => 'Plomberie',
                'min_budget'  => 10000,
                'max_budget'  => 20000,
                'urgency'     => 'high',
            ],
            [
                'title'       => 'Nettoyage complet appartement avant état des lieux',
                'description' => 'Je rends mon appartement F3 à Biyem-Assi vendredi. Besoin d\'une équipe pour nettoyage complet, vitres, salle de bain et cuisine.',
                'city'        => 'Yaoundé',
                'neighborhood'=> 'Biyem-Assi',
                'cat'         => 'Nettoyage',
                'min_budget'  => 20000,
                'max_budget'  => 45000,
                'urgency'     => 'medium',
            ],
            [
                'title'       => 'Jardin à entretenir mensuellement — Villa Garoua',
                'description' => 'Villa à Garoua avec grand jardin de 500m². Besoin d\'un jardinier mensuel : tonte, taille haies, arrosage et entretien allées.',
                'city'        => 'Garoua',
                'neighborhood'=> 'Centre-ville',
                'cat'         => 'Jardinage',
                'min_budget'  => 15000,
                'max_budget'  => 30000,
                'urgency'     => 'low',
            ],
            [
                'title'       => 'Réparation toiture — infiltrations eau de pluie',
                'description' => 'Infiltrations d\'eau importantes dans la chambre depuis les dernières pluies. Toiture en tôle à réparer, environ 3m² concernés à Logbaba.',
                'city'        => 'Douala',
                'neighborhood'=> 'Logbaba',
                'cat'         => 'Maçonnerie',
                'min_budget'  => 30000,
                'max_budget'  => 80000,
                'urgency'     => 'high',
            ],
            [
                'title'       => 'Pose carrelage salle de bain 8m²',
                'description' => 'Salle de bain à carreler suite à rénovation. 8m² de sol + murs jusqu\'à 1m50. Carrelage déjà acheté. Main-d\'œuvre uniquement à Bafoussam.',
                'city'        => 'Bafoussam',
                'neighborhood'=> 'Tamdja',
                'cat'         => 'Maçonnerie',
                'min_budget'  => 40000,
                'max_budget'  => 70000,
                'urgency'     => 'low',
            ],
            [
                'title'       => 'Montage et installation cuisine équipée',
                'description' => 'Cuisine en kit achetée à assembler et installer. Inclut placards hauts, plan de travail, évier et robinetterie. Appartement Bali, Douala.',
                'city'        => 'Douala',
                'neighborhood'=> 'Bali',
                'cat'         => 'Menuiserie',
                'min_budget'  => 35000,
                'max_budget'  => 65000,
                'urgency'     => 'medium',
            ],
            [
                'title'       => 'Entretien groupe électrogène 5KVA',
                'description' => 'Groupe électrogène qui n\'arrive plus à démarrer après chaque coupure. Besoin d\'un technicien pour vérification complète et entretien à Limbe.',
                'city'        => 'Limbe',
                'neighborhood'=> 'Down Beach',
                'cat'         => 'Électricité',
                'min_budget'  => 15000,
                'max_budget'  => 40000,
                'urgency'     => 'medium',
            ],
        ];

        foreach ($jobPosts as $index => $jobData) {
            $client       = $clientUsers->random();
            $cat          = $categories->firstWhere('name', $jobData['cat']) ?? $categories->first();

            $post = ServicePost::create([
                'client_id'      => $client->id,
                'category_id'    => $cat->id,
                'title'          => $jobData['title'],
                'description'    => $jobData['description'],
                'city'           => $jobData['city'],
                'neighborhood'   => $jobData['neighborhood'],
                'min_budget'     => $jobData['min_budget'],
                'max_budget'     => $jobData['max_budget'],
                'urgency'        => $jobData['urgency'],
                'status'         => 'open',
                'preferred_date' => now()->addDays(rand(1, 7)),
            ]);

            // Réponses des prestataires (simulation de concurrence)
            if ($index < 9) {
                $numResponses = rand(1, 4);
                $shuffled     = collect($providerUsers)->shuffle()->take($numResponses);
                foreach ($shuffled as $provider) {
                    $proposed = $jobData['max_budget'] - (rand(1, 4) * 2000);
                    ServicePostResponse::create([
                        'post_id'        => $post->id,
                        'provider_id'    => $provider->id,
                        'message'        => $this->getRandomResponse($provider->name, $jobData['title']),
                        'proposed_price' => max($proposed, $jobData['min_budget']),
                        'status'         => 'pending',
                    ]);
                }
            }
        }

        $this->command->info('✅ ' . count($providerUsers) . ' prestataires + ' . count($jobPosts) . ' demandes de service créés !');
    }

    private function getRandomResponse(string $name, string $jobTitle): string
    {
        $responses = [
            "Bonjour, je suis {$name}, disponible pour cette intervention. Je peux intervenir dans les 24h. Devis définitif après constatation sur place.",
            "Bonjour ! Expert dans ce type de travaux depuis plus de 5 ans. Matériel de qualité utilisé, garantie sur toutes mes interventions. Contactez-moi.",
            "Disponible ce week-end pour : {$jobTitle}. Travail soigné et propre, je remets en état impeccable. Prix tout inclus, pas de surprise.",
            "Professionnel certifié HMC, je prends en charge ce type d'intervention régulièrement. Références disponibles sur demande. Intervention rapide garantie.",
        ];

        return $responses[array_rand($responses)];
    }
}
