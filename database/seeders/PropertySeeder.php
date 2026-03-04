<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PropertySeeder extends Seeder
{
    /**
     * Images réalistes par catégorie (Unsplash, ambiance africaine/tropicale)
     */
    private array $imagesByCategory = [
        'Chambre' => [
            'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&q=80&w=800',
        ],
        'Studio' => [
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&q=80&w=800',
        ],
        'Appartement' => [
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1516455590571-18256e5bb9ff?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&q=80&w=800',
        ],
        'Maison' => [
            'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&q=80&w=800',
        ],
        'Villa' => [
            'https://images.unsplash.com/photo-1605276374104-dee2a0ed3cd6?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&q=80&w=800',
        ],
    ];

    /**
     * Annonces camerounaises réalistes
     */
    private array $properties = [
        // ─── DOUALA ───────────────────────────────────────────────────────────────
        [
            'title' => 'Chambre Moderne - Makepe, Douala',
            'city' => 'Douala',
            'location' => 'Makepe, Douala',
            'region' => 'Littoral',
            'category' => 'Chambre',
            'etat' => 'Bon état',
            'price' => 25000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 15,
            'description' => 'Belle chambre meublée dans un appartement partagé à Makepe. Quartier calme et sécurisé, proche de l\'Université de Douala. Eau et électricité incluses.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Wi-Fi'],
        ],
        [
            'title' => 'Chambre Meublée - Akwa, Douala',
            'city' => 'Douala',
            'location' => 'Akwa, Douala',
            'region' => 'Littoral',
            'category' => 'Chambre',
            'etat' => 'Meublé',
            'price' => 30000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 18,
            'description' => 'Chambre entièrement meublée (lit, armoire, table) en plein cœur d\'Akwa. Accès aux commerces, restaurants et transports en commun.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Gardiennage'],
        ],
        [
            'title' => 'Studio Haut Standing - Bonanjo, Douala',
            'city' => 'Douala',
            'location' => 'Bonanjo, Douala',
            'region' => 'Littoral',
            'category' => 'Studio',
            'etat' => 'Neuf',
            'price' => 75000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 40,
            'description' => 'Studio neuf avec cuisine américaine équipée à Bonanjo, quartier des affaires de Douala. Clim, Wi-Fi haut débit, sécurité 24h/24.',
            'amenities' => ['Climatisation', 'Wi-Fi', 'Sécurité 24/7', 'Parking'],
        ],
        [
            'title' => 'Studio Climatisé 35m² - Deido, Douala',
            'city' => 'Douala',
            'location' => 'Deido, Douala',
            'region' => 'Littoral',
            'category' => 'Studio',
            'etat' => 'Rénové',
            'price' => 55000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 35,
            'description' => 'Studio rénové et lumineux à Deido. Cuisine intégrée, salle d\'eau moderne. Proximité du port de Douala.',
            'amenities' => ['Climatisation', 'Eau courante', 'Électricité permanente'],
        ],
        [
            'title' => 'Appartement F3 - Bonapriso, Douala',
            'city' => 'Douala',
            'location' => 'Bonapriso, Douala',
            'region' => 'Littoral',
            'category' => 'Appartement',
            'etat' => 'Bon état',
            'price' => 180000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area' => 110,
            'description' => 'Bel appartement F3 dans la résidence Le Palmier à Bonapriso. Standing élevé, gardien, parking sécurisé. Quartier résidentiel prisé.',
            'amenities' => ['Climatisation', 'Parking', 'Sécurité 24/7', 'Groupe électrogène', 'Gardiennage'],
        ],
        [
            'title' => 'Appartement F2 - Makepe, Douala',
            'city' => 'Douala',
            'location' => 'Makepe, Douala',
            'region' => 'Littoral',
            'category' => 'Appartement',
            'etat' => 'Rénové',
            'price' => 100000,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'area' => 75,
            'description' => 'Appartement F2 entièrement rénové à Makepe. Peinture fraîche, carrelage neuf, cuisine refaite. Accès facile depuis le boulevard...',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Parking'],
        ],
        [
            'title' => 'Appartement Meublé - Bali, Douala',
            'city' => 'Douala',
            'location' => 'Bali, Douala',
            'region' => 'Littoral',
            'category' => 'Appartement',
            'etat' => 'Meublé',
            'price' => 150000,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area' => 90,
            'description' => 'Appartement haut standing entièrement meublé à Bali. Cuisine équipée, deux salles de bain, balcon avec vue. Idéal pour expatriés.',
            'amenities' => ['Climatisation', 'Wi-Fi', 'Parking', 'Balcon', 'Cuisine équipée'],
        ],
        [
            'title' => 'Maison F4 avec Cour - Logbaba, Douala',
            'city' => 'Douala',
            'location' => 'Logbaba, Douala',
            'region' => 'Littoral',
            'category' => 'Maison',
            'etat' => 'Bon état',
            'price' => 200000,
            'bedrooms' => 4,
            'bathrooms' => 2,
            'area' => 140,
            'description' => 'Grande maison familiale à Logbaba avec cour clôturée et jardin. 4 chambres, salon, cuisine moderne. Quartier sécurisé avec gardien.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Gardiennage', 'Jardin', 'Parking'],
        ],
        [
            'title' => 'Villa de Prestige - Bonapriso, Douala',
            'city' => 'Douala',
            'location' => 'Bonapriso, Douala',
            'region' => 'Littoral',
            'category' => 'Villa',
            'etat' => 'Neuf',
            'price' => 650000,
            'bedrooms' => 5,
            'bathrooms' => 4,
            'area' => 320,
            'description' => 'Superbe villa neuve dans le quartier résidentiel de Bonapriso. 5 chambres dont suite parentale, piscine, jardin paysager, 2 parkings couverts.',
            'amenities' => ['Climatisation', 'Parking', 'Sécurité 24/7', 'Groupe électrogène', 'Gardiennage', 'Jardin', 'Cuisine équipée'],
        ],

        // ─── YAOUNDÉ ──────────────────────────────────────────────────────────────
        [
            'title' => 'Chambre Bon état - Mimboman, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Mimboman, Yaoundé',
            'region' => 'Centre',
            'category' => 'Chambre',
            'etat' => 'Bon état',
            'price' => 20000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 14,
            'description' => 'Chambre propre et bien entretenue à Mimboman. Accès cuisine commune. Idéale pour étudiant, proche du campus de l\'Université de Yaoundé I.',
            'amenities' => ['Eau courante', 'Électricité permanente'],
        ],
        [
            'title' => 'Studio Meublé - Bastos, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Bastos, Yaoundé',
            'region' => 'Centre',
            'category' => 'Studio',
            'etat' => 'Meublé',
            'price' => 80000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 42,
            'description' => 'Studio de luxe meublé dans la résidentielle Bastos (quartier des ambassades). Tout confort : clim, Wi-Fi fibre, cuisine équipée, parking.',
            'amenities' => ['Climatisation', 'Wi-Fi', 'Parking', 'Sécurité 24/7', 'Cuisine équipée'],
        ],
        [
            'title' => 'Appartement F3 - Omnisport, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Omnisport, Yaoundé',
            'region' => 'Centre',
            'category' => 'Appartement',
            'etat' => 'Rénové',
            'price' => 130000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area' => 95,
            'description' => 'Appartement F3 rénové à Omnisport, à 5 min du Palais des Sports. Carrelage neuf, peinture fraîche. Résidence avec gardien et groupe électrogène.',
            'amenities' => ['Groupe électrogène', 'Gardiennage', 'Eau courante', 'Électricité permanente'],
        ],
        [
            'title' => 'Appartement F2 - Biyem-Assi, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Biyem-Assi, Yaoundé',
            'region' => 'Centre',
            'category' => 'Appartement',
            'etat' => 'Bon état',
            'price' => 90000,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'area' => 70,
            'description' => 'Appartement F2 bien situé à Biyem-Assi. Proche du marché, des écoles et pharmacies. Gardien en journée, eau et électricité stables.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Gardiennage'],
        ],
        [
            'title' => 'Maison F5 Clôturée - Nsimeyong, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Nsimeyong, Yaoundé',
            'region' => 'Centre',
            'category' => 'Maison',
            'etat' => 'Bon état',
            'price' => 250000,
            'bedrooms' => 5,
            'bathrooms' => 3,
            'area' => 180,
            'description' => 'Belle maison familiale à Nsimeyong avec grand séjour, 5 chambres, 3 douches et grande cour clôturée. Quartier calme, idéal pour expatriés.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Jardin', 'Parking', 'Gardiennage'],
        ],
        [
            'title' => 'Villa Standing 4 Ch. - Bastos, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Bastos, Yaoundé',
            'region' => 'Centre',
            'category' => 'Villa',
            'etat' => 'Neuf',
            'price' => 750000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'area' => 280,
            'description' => 'Villa haut standing à Bastos (quartier diplomatique). 4 chambres, salon avec vue, terrasse, jardin arborisé, groupe électrogène 25KVA, sécurité.',
            'amenities' => ['Climatisation', 'Parking', 'Sécurité 24/7', 'Groupe électrogène', 'Gardiennage', 'Jardin', 'Balcon'],
        ],
        [
            'title' => 'Appartement Neuf F4 - Mendong, Yaoundé',
            'city' => 'Yaoundé',
            'location' => 'Mendong, Yaoundé',
            'region' => 'Centre',
            'category' => 'Appartement',
            'etat' => 'Neuf',
            'price' => 160000,
            'bedrooms' => 4,
            'bathrooms' => 2,
            'area' => 115,
            'description' => 'Appartement neuf F4 dans résidence fermée à Mendong. Finitions de qualité, carrelage en marbre, cuisine ouverte. Groupe électrogène inclus.',
            'amenities' => ['Groupe électrogène', 'Parking', 'Sécurité 24/7', 'Cuisine équipée', 'Eau courante'],
        ],

        // ─── BAFOUSSAM ────────────────────────────────────────────────────────────
        [
            'title' => 'Chambre à Louer - Centre Bafoussam',
            'city' => 'Bafoussam',
            'location' => 'Centre, Bafoussam',
            'region' => 'Ouest',
            'category' => 'Chambre',
            'etat' => 'Bon état',
            'price' => 15000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 12,
            'description' => 'Chambre simple et propre au centre de Bafoussam. Accès aux marchés, gare routière et commerces. Eau incluse.',
            'amenities' => ['Eau courante', 'Électricité permanente'],
        ],
        [
            'title' => 'Studio Rénové - Tamdja, Bafoussam',
            'city' => 'Bafoussam',
            'location' => 'Tamdja, Bafoussam',
            'region' => 'Ouest',
            'category' => 'Studio',
            'etat' => 'Rénové',
            'price' => 45000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 32,
            'description' => 'Studio rénové dans quartier Tamdja de Bafoussam. Lumineux et bien aéré. Cuisine aménagée, douche séparée. Calme et sécurisé.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Parking'],
        ],
        [
            'title' => 'Appartement F3 - Kouoptamo, Bafoussam',
            'city' => 'Bafoussam',
            'location' => 'Kouoptamo, Bafoussam',
            'region' => 'Ouest',
            'category' => 'Appartement',
            'etat' => 'Bon état',
            'price' => 90000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area' => 95,
            'description' => 'Appartement F3 spacieux à Kouoptamo. Grand salon, 3 chambres, 2 douches. Résidence clôturée avec parking. Quartier résidentiel calme.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Parking', 'Gardiennage'],
        ],
        [
            'title' => 'Maison 4 Chambres - Famla, Bafoussam',
            'city' => 'Bafoussam',
            'location' => 'Famla, Bafoussam',
            'region' => 'Ouest',
            'category' => 'Maison',
            'etat' => 'Bon état',
            'price' => 150000,
            'bedrooms' => 4,
            'bathrooms' => 2,
            'area' => 130,
            'description' => 'Maison familiale à Famla avec grande cour arborée. 4 chambres, salon, cuisine. Résidence calme, loin du bruit de la ville.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Jardin', 'Parking'],
        ],

        // ─── BAMENDA ──────────────────────────────────────────────────────────────
        [
            'title' => 'Appartement F2 - Commercial Ave, Bamenda',
            'city' => 'Bamenda',
            'location' => 'Commercial Avenue, Bamenda',
            'region' => 'Nord-Ouest',
            'category' => 'Appartement',
            'etat' => 'Bon état',
            'price' => 80000,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'area' => 65,
            'description' => 'Appartement F2 bien situé sur Commercial Avenue, Bamenda. Proche des commerces, restaurants et banques. Eau et électricité stables.',
            'amenities' => ['Eau courante', 'Électricité permanente', 'Parking'],
        ],
        [
            'title' => 'Studio Meublé - Mile 4, Bamenda',
            'city' => 'Bamenda',
            'location' => 'Mile 4, Bamenda',
            'region' => 'Nord-Ouest',
            'category' => 'Studio',
            'etat' => 'Meublé',
            'price' => 50000,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area' => 38,
            'description' => 'Studio meublé confortable à Mile 4. Cuisine intégrée, lit double, clim. Accès facile aux universités et hôpitaux de Bamenda.',
            'amenities' => ['Climatisation', 'Eau courante', 'Électricité permanente', 'Wi-Fi'],
        ],

        // ─── GAROUA ───────────────────────────────────────────────────────────────
        [
            'title' => 'Appartement F3 - Plateau, Garoua',
            'city' => 'Garoua',
            'location' => 'Plateau, Garoua',
            'region' => 'Nord',
            'category' => 'Appartement',
            'etat' => 'Bon état',
            'price' => 85000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area' => 90,
            'description' => 'Appartement F3 sur le Plateau de Garoua. Climatisé, bien entretenu, quartier résidentiel. Proche des administrations et du marché central.',
            'amenities' => ['Climatisation', 'Eau courante', 'Électricité permanente', 'Gardiennage'],
        ],
        [
            'title' => 'Villa Meublée - Centre-ville, Garoua',
            'city' => 'Garoua',
            'location' => 'Centre-ville, Garoua',
            'region' => 'Nord',
            'category' => 'Villa',
            'etat' => 'Meublé',
            'price' => 400000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'area' => 220,
            'description' => 'Grande villa meublée à Garoua. Entièrement climatisée, groupe électrogène, puits d\'eau, jardin. Idéale famille ou expatriés.',
            'amenities' => ['Climatisation', 'Groupe électrogène', 'Jardin', 'Parking', 'Sécurité 24/7', 'Gardiennage'],
        ],

        // ─── KRIBI / LIMBE ────────────────────────────────────────────────────────
        [
            'title' => 'Villa Bord de Mer - Kribi',
            'city' => 'Kribi',
            'location' => 'Front de Mer, Kribi',
            'region' => 'Sud',
            'category' => 'Villa',
            'etat' => 'Bon état',
            'price' => 500000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'area' => 250,
            'description' => 'Magnifique villa en bord de mer à Kribi. Vue sur l\'océan Atlantique, grande terrasse, jardin cocotiers. Idéale vacances ou résidence principale.',
            'amenities' => ['Climatisation', 'Jardin', 'Parking', 'Balcon', 'Groupe électrogène'],
        ],
        [
            'title' => 'Appartement Vue Mer - Limbe',
            'city' => 'Limbe',
            'location' => 'Down Beach, Limbe',
            'region' => 'Sud-Ouest',
            'category' => 'Appartement',
            'etat' => 'Bon état',
            'price' => 120000,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area' => 85,
            'description' => 'Appartement avec vue sur la baie de Limbe. 2 chambres climatisées, terrasse, parking. Proche de Limbe Wildlife Centre et plages.',
            'amenities' => ['Climatisation', 'Balcon', 'Parking', 'Eau courante'],
        ],
    ];

    public function run(): void
    {
        // Récupérer ou créer les agents/bailleurs
        $agents = User::where('role', 'agent')->orWhere('role', 'bailleur')->get();

        if ($agents->isEmpty()) {
            $agents = collect([
                User::firstOrCreate(['email' => 'bailleur@home.cm'], [
                    'name' => 'Pierre Ndoumbe',
                    'password' => Hash::make('password'),
                    'role' => 'bailleur',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'city' => 'Douala',
                    'phone' => '+237 699 000 001',
                ]),
            ]);
        }

        // Supprimer les anciennes propriétés de test
        Property::where('currency', 'XAF')->delete();

        foreach ($this->properties as $data) {
            $agent = $agents->random();
            $slug = Str::slug($data['title']).'-'.Str::random(6);

            $property = Property::create([
                'user_id' => $agent->id,
                'title' => $data['title'],
                'slug' => $slug,
                'type' => 'rent',
                'status' => 'active',
                'category' => $data['category'],
                'etat' => $data['etat'],
                'amenities' => $data['amenities'],
                'features' => $data['amenities'],
                'price' => $data['price'],
                'currency' => 'XAF',
                'description' => $data['description'],
                'location' => $data['location'],
                'city' => $data['city'],
                'region' => $data['region'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'area' => $data['area'],
                'construction_year' => rand(2010, 2024),
                'views_count' => rand(10, 800),
            ]);

            // Image réaliste par catégorie
            $images = $this->imagesByCategory[$data['category']];
            $imgUrl = $images[array_rand($images)];

            PropertyImage::create([
                'property_id' => $property->id,
                'path' => $imgUrl,
                'is_primary' => true,
                'order' => 0,
            ]);
        }

        $this->command->info('✅ '.count($this->properties).' annonces camerounaises créées avec succès !');
    }
}
