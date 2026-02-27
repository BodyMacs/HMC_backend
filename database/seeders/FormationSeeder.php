<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Formation;
use Illuminate\Database\Seeder;

class FormationSeeder extends Seeder
{
    public function run(): void
    {
        Formation::create([
            'title' => 'Certification Agent Immobilier',
            'description' => "Une formation complète et reconnue pour maîtriser les fondamentaux du métier d'agent immobilier au Cameroun.",
            'badge' => "Diplôme d'État",
            'price' => 50000,
            'modules' => [
                [
                    'title' => 'Module 1: Marché Immobilier Camerounais',
                    'duration' => '2h30',
                    'lessons' => [
                        'Secteurs clés (Douala, Yaoundé, etc.)',
                        'Analyse de la concurrence',
                        'Tendances 2024-2025',
                        'Stratégies de positionnement',
                    ],
                ],
                [
                    'title' => 'Module 2: Techniques de Vente et Négociation',
                    'duration' => '3h',
                    'lessons' => [
                        'Prospecter efficacement',
                        'Arguments de vente',
                        'Gestion des objections',
                        'Clôture de contrats',
                    ],
                ],
                [
                    'title' => 'Module 3: Aspects Juridiques et Contrats',
                    'duration' => '2h45',
                    'lessons' => [
                        'Législation immobilière Cam.',
                        'Rédaction de baux',
                        'Mandats de vente',
                        'Fiscalité immobilière',
                    ],
                ],
            ],
            'status' => 'active',
        ]);
    }
}
