<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => "Canapé d'angle Moderne",
                'price' => 450000,
                'old_price' => 500000,
                'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&q=80&w=800',
                'category' => 'Meubles',
                'rating' => 5,
                'reviews_count' => 124,
                'location' => 'Douala, Akwa',
                'is_featured' => true,
                'badge' => '-10%',
                'status' => 'active',
            ],
            [
                'name' => 'Lampe Suspendue Industrielle',
                'price' => 45000,
                'old_price' => null,
                'image' => asset('storage/meuble/lampe suspendu.webp'),
                'category' => 'Éclairage',
                'rating' => 4,
                'reviews_count' => 89,  
                'location' => 'Yaoundé, Bastos',
                'is_featured' => true,
                'badge' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Kit Caméras de Surveillance',
                'price' => 120000,
                'old_price' => 150000,
                'image' => asset('storage/meuble/kit camera.jfif'),
                'category' => 'Sécurité',
                'rating' => 5,
                'reviews_count' => 210,
                'location' => 'Douala, Bonapriso',
                'is_featured' => true,
                'badge' => 'Promo',
                'status' => 'active',
            ],
            [
                'name' => 'Table Basse en Verre',
                'price' => 85000,
                'old_price' => 95000,
                'image' => asset('storage/meuble/table basse en verre.webp'),
                'category' => 'Meubles',
                'rating' => 4,
                'reviews_count' => 45,
                'location' => 'Yaoundé, Omnisports',
                'is_featured' => false,
                'badge' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Vase Céramique Artisanal',
                'price' => 25000,
                'old_price' => null,
                'image' => asset('storage/meuble/vase ceramique.webp'),
                'category' => 'Décoration',
                'rating' => 5,
                'reviews_count' => 67,
                'location' => 'Bafoussam',
                'is_featured' => false,
                'badge' => 'Nouveau',
                'status' => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
