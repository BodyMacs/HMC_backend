<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Plomberie', 'icon' => 'fas fa-wrench'],
            ['name' => 'Électricité', 'icon' => 'fas fa-bolt'],
            ['name' => 'Maçonnerie', 'icon' => 'fas fa-hard-hat'],
            ['name' => 'Menuiserie', 'icon' => 'fas fa-hammer'],
            ['name' => 'Peinture', 'icon' => 'fas fa-paint-roller'],
            ['name' => 'Nettoyage', 'icon' => 'fas fa-broom'],
            ['name' => 'Jardinage', 'icon' => 'fas fa-seedling'],
            ['name' => 'Climatisation', 'icon' => 'fas fa-snowflake'],
        ];

        foreach ($categories as $category) {
            ServiceCategory::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'icon' => $category['icon'],
            ]);
        }
    }
}
