<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Administrateur',
            'email' => 'admin@home.cm',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ]);

        // Bailleur user
        User::create([
            'name' => 'Bailleur Test',
            'email' => 'bailleur@home.cm',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'bailleur',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ]);

        // Locataire user
        User::create([
            'name' => 'Locataire Test',
            'email' => 'locataire@home.cm',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'locataire',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ]);

        // Prestataire user
        User::create([
            'name' => 'Prestataire Test',
            'email' => 'prestataire@home.cm',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'prestataire',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ]);

        // Agents immobiliers (x4)
        $agents = [
            [
                'name' => 'Jean-Baptiste Mbarga',
                'email' => 'agent@home.cm',
                'city' => 'Yaoundé',
                'bio' => 'Expert en immobilier résidentiel à Yaoundé depuis 8 ans.',
                'avatar' => 'https://i.pravatar.cc/150?img=11',
            ],
            [
                'name' => 'Marie-Claire Ngo Biyong',
                'email' => 'agent2@home.cm',
                'city' => 'Douala',
                'bio' => 'Spécialiste des biens de prestige à Bonanjo et Akwa.',
                'avatar' => 'https://i.pravatar.cc/150?img=21',
            ],
            [
                'name' => 'Patrick Essomba',
                'email' => 'agent3@home.cm',
                'city' => 'Bafoussam',
                'bio' => 'Agent certifié pour la région Ouest du Cameroun.',
                'avatar' => 'https://i.pravatar.cc/150?img=33',
            ],
            [
                'name' => 'Aïcha Aboubakar',
                'email' => 'agent4@home.cm',
                'city' => 'Garoua',
                'bio' => 'Première agente immobilière du Grand Nord, 5 ans d\'expérience.',
                'avatar' => 'https://i.pravatar.cc/150?img=47',
            ],
        ];

        foreach ($agents as $agentData) {
            User::create([
                'name' => $agentData['name'],
                'email' => $agentData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'agent',
                'status' => 'active',
                'avatar' => $agentData['avatar'],
                'remember_token' => Str::random(10),
            ]);
        }
    }
}
