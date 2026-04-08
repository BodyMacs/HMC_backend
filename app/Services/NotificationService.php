<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;

/**
 * NotificationService — Façade centralisée pour envoyer des notifications.
 * Utilise Notification::send() en interne avec des messages prédéfinis.
 */
class NotificationService
{
    // ══════════════════════════════════════════════════════════════════════════
    // VISITES
    // ══════════════════════════════════════════════════════════════════════════

    /** Notifie le client que sa visite a été réservée avec succès */
    public static function visitBooked(int $userId, string $propertyTitle, int $visitId): void
    {
        Notification::send($userId, 'visit',
            'Visite réservée',
            "Votre visite pour « $propertyTitle » a été enregistrée. Un agent vous contactera pour confirmer.",
            [
                'icon'         => 'calendar-plus',
                'icon_bg'      => 'bg-green-100',
                'icon_color'   => 'text-green-600',
                'action_label' => 'Voir mes visites',
                'action_link'  => '/mes-visites',
                'reference_type' => 'App\Models\Visit',
                'reference_id'   => $visitId,
            ]
        );
    }

    /** Notifie le client que sa visite a été confirmée par l'agent */
    public static function visitConfirmed(int $userId, string $propertyTitle, string $scheduledAt, int $visitId): void
    {
        Notification::send($userId, 'visit',
            'Visite confirmée ✅',
            "Votre visite pour « $propertyTitle » est confirmée pour le $scheduledAt.",
            [
                'icon'         => 'calendar-check',
                'icon_bg'      => 'bg-green-100',
                'icon_color'   => 'text-green-600',
                'action_label' => 'Voir le détail',
                'action_link'  => '/mes-visites',
                'reference_type' => 'App\Models\Visit',
                'reference_id'   => $visitId,
            ]
        );
    }

    /** Notifie le client que sa visite a été annulée */
    public static function visitCancelled(int $userId, string $propertyTitle, int $visitId): void
    {
        Notification::send($userId, 'alert',
            'Visite annulée',
            "Votre visite pour « $propertyTitle » a été annulée.",
            [
                'icon'         => 'calendar-times',
                'icon_bg'      => 'bg-red-100',
                'icon_color'   => 'text-red-500',
                'action_label' => 'Reprogrammer',
                'action_link'  => '/annonces',
                'reference_type' => 'App\Models\Visit',
                'reference_id'   => $visitId,
            ]
        );
    }

    /** Notifie l'agent qu'une nouvelle visite lui est assignée */
    public static function visitAssignedToAgent(int $agentId, string $propertyTitle, string $scheduledAt, int $visitId): void
    {
        Notification::send($agentId, 'visit',
            'Nouvelle visite assignée',
            "Une visite pour « $propertyTitle » vous a été assignée le $scheduledAt.",
            [
                'icon'         => 'user-check',
                'icon_bg'      => 'bg-blue-100',
                'icon_color'   => 'text-blue-600',
                'action_label' => 'Voir la visite',
                'action_link'  => '/agent/visites',
                'reference_type' => 'App\Models\Visit',
                'reference_id'   => $visitId,
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DOSSIERS DE CANDIDATURE
    // ══════════════════════════════════════════════════════════════════════════

    /** Notifie le client que son dossier a été soumis */
    public static function applicationSubmitted(int $userId, string $propertyTitle, int $applicationId): void
    {
        Notification::send($userId, 'application',
            'Dossier soumis',
            "Votre dossier pour « $propertyTitle » a été soumis. Un agent va l'analyser.",
            [
                'action_label'   => 'Suivi dossier',
                'action_link'    => '/mon-dossier',
                'reference_type' => 'App\Models\RentalApplication',
                'reference_id'   => $applicationId,
            ]
        );
    }

    /** Notifie le client que son dossier a été validé */
    public static function applicationValidated(int $userId, string $propertyTitle, int $applicationId): void
    {
        Notification::send($userId, 'application',
            'Dossier validé 🎉',
            "Félicitations ! Votre dossier pour « $propertyTitle » a été validé. Vous pouvez maintenant procéder au paiement initial.",
            [
                'icon'           => 'check-circle',
                'icon_bg'        => 'bg-emerald-100',
                'icon_color'     => 'text-emerald-600',
                'action_label'   => 'Procéder au paiement',
                'action_link'    => '/mon-dossier',
                'reference_type' => 'App\Models\RentalApplication',
                'reference_id'   => $applicationId,
            ]
        );
    }

    /** Notifie le client que son dossier a été rejeté */
    public static function applicationRejected(int $userId, string $propertyTitle, string $reason, int $applicationId): void
    {
        Notification::send($userId, 'alert',
            'Dossier non retenu',
            "Votre dossier pour « $propertyTitle » n'a pas été retenu. Motif : $reason",
            [
                'icon'           => 'times-circle',
                'icon_bg'        => 'bg-red-100',
                'icon_color'     => 'text-red-500',
                'action_label'   => 'Voir d\'autres biens',
                'action_link'    => '/annonces',
                'reference_type' => 'App\Models\RentalApplication',
                'reference_id'   => $applicationId,
            ]
        );
    }

    /** Notifie l'agent qu'un nouveau dossier lui est assigné */
    public static function applicationAssignedToAgent(int $agentId, string $applicantName, string $propertyTitle, int $applicationId): void
    {
        Notification::send($agentId, 'application',
            'Nouveau dossier à traiter',
            "$applicantName a soumis un dossier pour « $propertyTitle ».",
            [
                'action_label'   => 'Analyser le dossier',
                'action_link'    => '/agent/dossiers',
                'reference_type' => 'App\Models\RentalApplication',
                'reference_id'   => $applicationId,
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PAIEMENTS & LOCATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /** Notifie le client que sa location est active */
    public static function rentalActivated(int $userId, string $propertyTitle, int $rentalId): void
    {
        Notification::send($userId, 'rental',
            'Location activée 🏠',
            "Bienvenue dans votre nouveau logement ! Votre location de « $propertyTitle » est maintenant active.",
            [
                'icon'           => 'key',
                'icon_bg'        => 'bg-indigo-100',
                'icon_color'     => 'text-indigo-600',
                'action_label'   => 'Mon espace locataire',
                'action_link'    => '/locataire/dashboard',
                'reference_type' => 'App\Models\Rental',
                'reference_id'   => $rentalId,
            ]
        );
    }

    /** Notifie le bailleur qu'un paiement de loyer a été reçu */
    public static function rentPaymentReceived(int $userId, string $propertyTitle, string $amount, int $rentalId): void
    {
        Notification::send($userId, 'payment',
            'Paiement de loyer reçu',
            "Un paiement de $amount FCFA a été reçu pour « $propertyTitle ».",
            [
                'action_label'   => 'Voir les finances',
                'action_link'    => '/bailleur/finances',
                'reference_type' => 'App\Models\Rental',
                'reference_id'   => $rentalId,
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYSTÈME
    // ══════════════════════════════════════════════════════════════════════════

    /** Notification de bienvenue */
    public static function welcome(int $userId, string $userName): void
    {
        Notification::send($userId, 'system',
            'Bienvenue sur HomeCameroon ! 👋',
            "Bonjour $userName, votre compte a été créé avec succès. Commencez à explorer nos biens.",
            [
                'action_label' => 'Explorer les biens',
                'action_link'  => '/annonces',
            ]
        );
    }

    /** Notification de publication d'un bien (bailleur) */
    public static function propertyPublished(int $userId, string $propertyTitle, int $propertyId): void
    {
        Notification::send($userId, 'system',
            'Bien publié avec succès',
            "Votre bien « $propertyTitle » est maintenant visible sur la plateforme.",
            [
                'icon'           => 'home',
                'icon_bg'        => 'bg-green-100',
                'icon_color'     => 'text-green-600',
                'action_label'   => 'Voir l\'annonce',
                'action_link'    => "/annonces/$propertyId",
                'reference_type' => 'App\Models\Property',
                'reference_id'   => $propertyId,
            ]
        );
    }

    /** Notification d'audit planifié (bailleur) */
    public static function auditScheduled(int $userId, string $propertyTitle, string $scheduledAt): void
    {
        Notification::send($userId, 'visit',
            'Audit terrain planifié',
            "Un agent HMC va effectuer une visite d'audit de « $propertyTitle » le $scheduledAt.",
            [
                'icon'       => 'camera',
                'icon_bg'    => 'bg-purple-100',
                'icon_color' => 'text-purple-600',
            ]
        );
    }
}
