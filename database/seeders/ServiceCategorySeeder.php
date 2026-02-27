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
            ['name' => 'Plomberie', 'icon' => 'wrench'],
            ['name' => 'Électricité', 'icon' => 'bolt'],
            ['name' => 'Maçonnerie', 'icon' => 'hard-hat'],
            ['name' => 'Menuiserie', 'icon' => 'hammer'],
            ['name' => 'Peinture', 'icon' => 'paint-roller'],
            ['name' => 'Nettoyage', 'icon' => 'broom'],
            ['name' => 'Jardinage', 'icon' => 'seedling'],
            ['name' => 'Climatisation', 'icon' => 'snowflake'],
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
