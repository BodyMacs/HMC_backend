<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Données camerounaises réalistes
     */
    private static array $camCities = [
        'Douala',
        'Yaoundé',
        'Bafoussam',
        'Bamenda',
        'Garoua',
        'Maroua',
        'Ngaoundéré',
        'Bertoua',
        'Ebolowa',
        'Kumba',
    ];

    private static array $camQuarters = [
        'Douala' => [
            'Bonanjo',
            'Akwa',
            'Makepe',
            'Deido',
            'Bonapriso',
            'Bassa',
            'PK14',
            'Ndokoti',
            'Logbaba',
            'Kotto',
        ],
        'Yaoundé' => [
            'Bastos',
            'Mvan',
            'Omnisport',
            'Essos',
            'Nsimeyong',
            'Mvog-Mbi',
            'Santa Barbara',
            'Mimboman',
            'Biyem-Assi',
            'Mendong',
        ],
        'Bafoussam' => ['Centre', 'Tamdja', 'Kouoptamo', 'Ndiangdam', 'Famla'],
        'Bamenda' => ['Mile 4', 'Commercial Avenue', 'Up Station', 'Nitop'],
        'Garoua' => ['Centre-ville', 'Plateau', 'Yelwa'],
        'Maroua' => ['Centre', 'Domayo', 'Dougol'],
        'default' => ['Centre-ville', 'Quartier résidentiel'],
    ];

    private static array $categories = [
        'Chambre',
        'Studio',
        'Appartement',
        'Maison',
        'Villa',
    ];

    private static array $etats = ['Neuf', 'Bon état', 'Rénové', 'Meublé'];

    private static array $allAmenities = [
        'Climatisation',
        'Parking',
        'Sécurité 24/7',
        'Wi-Fi',
        'Eau courante',
        'Électricité permanente',
        'Gardiennage',
        'Groupe électrogène',
        'Balcon',
        'Jardin',
        'Cuisine équipée',
    ];

    private static array $descTemplates = [
        'Chambre' => 'Belle chambre {etat} dans un appartement {adj}, quartier {quarter}. Idéale pour étudiant ou jeune professionnel. {extra}',
        'Studio' => 'Studio {etat} de {area}m², entièrement équipé, situé à {quarter}. {extra}',
        'Appartement' => 'Appartement {etat} de {rooms} chambres à {quarter}. Résidence sécurisée, proche du marché et des transports. {extra}',
        'Maison' => 'Belle maison {etat} de {rooms} chambres avec cour clôturée à {quarter}. Idéale pour une famille. {extra}',
        'Villa' => 'Magnifique villa {etat} dans le quartier huppé de {quarter}. {rooms} chambres, {baths} salles de bain, standing élevé. {extra}',
    ];

    public function definition(): array
    {
        $category = $this->faker->randomElement(self::$categories);
        $city = $this->faker->randomElement(self::$camCities);
        $quarters = self::$camQuarters[$city] ?? self::$camQuarters['default'];
        $quarter = $this->faker->randomElement($quarters);
        $etat = $this->faker->randomElement(self::$etats);
        $bedrooms = match ($category) {
            'Chambre' => 1,
            'Studio' => 1,
            'Appartement' => $this->faker->numberBetween(2, 4),
            'Maison' => $this->faker->numberBetween(3, 5),
            'Villa' => $this->faker->numberBetween(4, 7),
        };
        $area = match ($category) {
            'Chambre' => $this->faker->numberBetween(12, 20),
            'Studio' => $this->faker->numberBetween(25, 45),
            'Appartement' => $this->faker->numberBetween(50, 120),
            'Maison' => $this->faker->numberBetween(100, 200),
            'Villa' => $this->faker->numberBetween(150, 400),
        };
        $price = match ($category) {
            'Chambre' => $this->faker->randomElement([15000, 20000, 25000, 30000]),
            'Studio' => $this->faker->randomElement([40000, 50000, 60000, 75000]),
            'Appartement' => $this->faker->randomElement([80000, 100000, 120000, 150000, 180000]),
            'Maison' => $this->faker->randomElement([150000, 200000, 250000, 300000]),
            'Villa' => $this->faker->randomElement([350000, 450000, 600000, 800000]),
        };

        $adjectives = ['calme', 'sécurisé', 'moderne', 'spacieux', 'bien entretenu'];
        $extras = [
            'Eau et électricité comprises.',
            'Proche des écoles et commerces.',
            'Accès facile aux transports en commun.',
            'Vue dégagée, lumineux.',
            'Gardien sur place.',
        ];

        $amenitiesCount = $this->faker->numberBetween(2, 6);
        $amenities = $this->faker->randomElements(self::$allAmenities, $amenitiesCount);
        $amenitiesDisplay = implode(', ', array_slice($amenities, 0, 3));

        $descTemplate = self::$descTemplates[$category];
        $description = str_replace(
            ['{etat}', '{adj}', '{quarter}', '{rooms}', '{baths}', '{area}', '{extra}'],
            [
                strtolower($etat),
                $this->faker->randomElement($adjectives),
                $quarter,
                $bedrooms,
                $this->faker->numberBetween(1, 2),
                $area,
                $this->faker->randomElement($extras),
            ],
            $descTemplate
        );

        $titlePatterns = [
            'Chambre' => '{etat} - Chambre à {quarter}, {city}',
            'Studio' => 'Studio {etat} {area}m² - {quarter}, {city}',
            'Appartement' => 'Appartement {rooms}P {etat} - {quarter}, {city}',
            'Maison' => 'Maison {rooms} chambres - {quarter}, {city}',
            'Villa' => 'Villa de standing {rooms} Ch. - {quarter}, {city}',
        ];
        $title = str_replace(
            ['{etat}', '{rooms}', '{area}', '{quarter}', '{city}'],
            [$etat, $bedrooms, $area, $quarter, $city],
            $titlePatterns[$category]
        );

        $slug = Str::slug($title . '-' . $this->faker->unique()->numberBetween(1000, 9999));

        return [
            'user_id' => fn() => User::factory(),
            'title' => $title,
            'slug' => $slug,
            'type' => 'rent',
            'status' => 'active',
            'category' => $category,
            'etat' => $etat,
            'amenities' => $amenities,
            'price' => $price,
            'currency' => 'XAF',
            'description' => $description,
            'location' => $quarter . ', ' . $city,
            'city' => $city,
            'region' => $this->camRegion($city),
            'features' => $amenities,
            'bedrooms' => $bedrooms,
            'bathrooms' => $this->faker->numberBetween(1, max(1, intdiv($bedrooms, 2))),
            'area' => $area,
            'construction_year' => $this->faker->numberBetween(2005, 2024),
            'views_count' => $this->faker->numberBetween(0, 500),
        ];
    }

    private function camRegion(string $city): string
    {
        return match ($city) {
            'Douala', 'Kumba' => 'Littoral / Sud-Ouest',
            'Yaoundé', 'Ebolowa' => 'Centre / Sud',
            'Bafoussam' => 'Ouest',
            'Bamenda' => 'Nord-Ouest',
            'Garoua', 'Maroua', 'Ngaoundéré' => 'Nord / Adamaoua',
            'Bertoua' => 'Est',
            default => 'Cameroun',
        };
    }
}
