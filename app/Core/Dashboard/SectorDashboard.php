<?php

declare(strict_types=1);

namespace Sesam\Core\Dashboard;

use Sesam\Core\Tenant\Sector;

final class SectorDashboard
{
    /**
     * @return array{
     *     activityTitle: string,
     *     kpis: list<array{key: string, label: string}>,
     *     quickActions: list<array{label: string, hint: string, href?: string, permission?: string}>,
     *     activityEmpty: string,
     *     upcomingTitle: string,
     *     workTitle: string,
     *     queueTitle: string,
     *     peopleTitle: string
     * }
     */
    public static function config(string $sector): array
    {
        return match ($sector) {
            Sector::LOYER => [
                'activityTitle' => 'Votre activité aujourd\'hui',
                'kpis' => [
                    ['key' => 'rent_due_today', 'label' => 'Échéances du jour'],
                    ['key' => 'rent_late', 'label' => 'Retards'],
                    ['key' => 'payments_received', 'label' => 'Paiements reçus'],
                    ['key' => 'leases_active', 'label' => 'Contrats actifs'],
                ],
                'quickActions' => [
                    ['label' => 'Nouveau contrat de bail', 'hint' => '', 'href' => '/immo/baux/nouveau', 'permission' => 'loyer.leases'],
                    ['label' => 'Ajouter un locataire', 'hint' => '', 'href' => '/clients/nouveau', 'permission' => 'clients.create'],
                    ['label' => 'Voir les échéances', 'hint' => '', 'href' => '/immo/loyers', 'permission' => 'loyer.view'],
                    ['label' => 'Retards', 'hint' => '', 'href' => '/immo/loyers?vue=retards', 'permission' => 'loyer.view'],
                ],
                'activityEmpty' => 'Les échéances et paiements apparaîtront ici avec l\'activité CRM.',
                'upcomingTitle' => 'Échéances',
                'workTitle' => 'Contrats',
                'queueTitle' => 'Paiements',
                'peopleTitle' => 'Locataires',
            ],
            default => [
                'activityTitle' => 'Votre activité aujourd\'hui',
                'kpis' => [
                    ['key' => 'appointments_today', 'label' => 'Rendez-vous du jour'],
                    ['key' => 'appointments_upcoming', 'label' => 'À venir'],
                    ['key' => 'payments_pending', 'label' => 'Paiements en attente'],
                    ['key' => 'clients_today', 'label' => 'Clients attendus'],
                ],
                'quickActions' => [
                    ['label' => 'Nouveau rendez-vous', 'hint' => '', 'href' => '/reservation/rendez-vous/nouveau', 'permission' => 'reservation.appointments'],
                    ['label' => 'Ajouter un client', 'hint' => '', 'href' => '/clients/nouveau', 'permission' => 'clients.create'],
                    ['label' => 'Enregistrer un paiement', 'hint' => '', 'href' => '/paiements/nouveau', 'permission' => 'payments.create'],
                    ['label' => 'Ajouter une prestation', 'hint' => '', 'href' => '/reservation/prestations/nouveau', 'permission' => 'reservation.services'],
                ],
                'activityEmpty' => 'Les rendez-vous confirmés et les paiements d\'acompte apparaîtront ici avec l\'activité CRM.',
                'upcomingTitle' => 'Rendez-vous',
                'workTitle' => 'Planning',
                'queueTitle' => 'Paiements',
                'peopleTitle' => 'Personnel',
            ],
        };
    }
}
