<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Notification::truncate(); // Nettoyer les anciennes if needed
        
        $users = User::all();
        if ($users->isEmpty()) {
            return;
        }

        $now = Carbon::now();

        foreach ($users as $user) {
            // Notifications de système
            Notification::send($user->id, 'system', 'Bienvenue sur Home Cameroon', 'Votre compte a été vérifié avec succès. Vous pouvez maintenant accéder à tous nos services.', [
                'action_label' => 'Voir mon profil',
                'action_link' => '/parametres'
            ]);

            // Notifications spécifiques selon les rôles
            if ($user->role === 'locataire' || $user->role === 'agent') {
                Notification::send($user->id, 'visit', 'Visite programmée', 'Votre visite pour la villa "Perle de Japoma" est confirmée pour le '. $now->copy()->addDays(2)->format('d/m/Y') .' à 14h00.', [
                    'action_label' => 'Détails de la visite',
                    'action_link' => '/locataire/mes-locations'
                ]);

                Notification::send($user->id, 'payment', 'Paiement reçu', 'Votre paiement de loyer de 150 000 FCFA a bien été traité.', [
                    'action_label' => 'Télécharger reçu',
                    'action_link' => '/locataire/mes-paiements'
                ]);
            }

            if ($user->role === 'bailleur') {
                Notification::send($user->id, 'application', 'Nouveau dossier locatif', 'Vous avez reçu un nouveau dossier de location pour votre appartement à Bonamoussadi.', [
                    'action_label' => 'Voir le dossier',
                    'action_link' => '/bailleur/mes-locataires'
                ]);
            }

            if ($user->role === 'prestataire') {
                Notification::send($user->id, 'message', 'Nouvelle opportunité', 'Un utilisateur a publié une demande de plomberie dans votre secteur. Soyez le premier à répondre !', [
                    'action_label' => 'Voir la demande',
                    'action_link' => '/marketplace/demandes'
                ]);
            }

            // Génériques non lues (pour simuler la bulle de noif)
            $notif = Notification::send($user->id, 'alert', 'Alerte sécurité', 'Une nouvelle connexion a été détectée sur votre compte.');
            $notif->is_read = false;
            $notif->created_at = $now->copy()->subMinutes(30);
            $notif->save();
            
            $notif2 = Notification::send($user->id, 'message', 'Nouveau message reçu', 'Vous avez reçu un message concernant votre annonce.', [
                'action_label' => 'Lire',
                'action_link' => '/messages'
            ]);
            $notif2->is_read = false;
            $notif2->created_at = $now->copy()->subMinutes(15);
            $notif2->save();
        }
    }
}
