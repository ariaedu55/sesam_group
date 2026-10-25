<?php

declare(strict_types=1);

namespace Sesam\Services\Agency;

final class AgencyCatalog
{
    public const MONTHLY_CAPACITY = 5;

    /** @var array<string, string> */
    public const STAGES = [
        'nouveau' => 'Nouveau prospect',
        'contacte' => 'Contacté',
        'rendez-vous' => 'Rendez-vous',
        'devis-envoye' => 'Devis envoyé',
        'negociation' => 'Négociation',
        'gagne' => 'Gagné',
        'projet-en-cours' => 'Projet en cours',
        'livre' => 'Livré',
        'perdu' => 'Perdu',
    ];

    /** @var list<string> Gagné, Projet en cours et Livré suivent les devis et projets. */
    public const MANUAL_STAGES = ['nouveau', 'contacte', 'rendez-vous', 'devis-envoye', 'negociation', 'perdu'];

    /** @var list<string> */
    public const CLIENT_STAGES = ['gagne', 'projet-en-cours', 'livre'];

    /** @var array<string, array{label: string, min: int}> */
    public const OFFERS = [
        'essentiel' => ['label' => 'Essentiel', 'min' => 200_000],
        'professionnel' => ['label' => 'Professionnel', 'min' => 450_000],
        'sur-mesure' => ['label' => 'Sur mesure', 'min' => 1],
    ];

    /** @var array<string, string> */
    public const SITE_PATHS = [
        'site-neuf' => 'Site neuf',
        'site-existant' => 'Site déjà là',
    ];

    /** @var array<string, string> */
    public const QUOTE_STATUSES = [
        'draft' => 'Brouillon',
        'sent' => 'Envoyé',
        'accepted' => 'Accepté',
        'refused' => 'Refusé',
    ];

    /** @var array<string, string> */
    public const PROJECT_STATUSES = [
        'planned' => 'À démarrer',
        'in_progress' => 'En cours',
        'delivered' => 'Livré',
    ];

    /** @var array<string, string> */
    public const INVOICE_STATUSES = [
        'issued' => 'À payer',
        'paid' => 'Payée',
    ];

    /** @var array<string, string> */
    public const PAYMENT_METHODS = [
        'wave' => 'Wave',
        'orange-money' => 'Orange Money',
        'especes' => 'Espèces',
        'virement' => 'Virement',
    ];

    /**
     * @return array<string, string>
     */
    public static function activities(): array
    {
        static $activities = null;
        if ($activities === null) {
            $catalog = require dirname(__DIR__, 2) . '/Views/public/home-catalog.php';
            $activities = is_array($catalog['activities'] ?? null) ? $catalog['activities'] : [];
        }

        return $activities;
    }
}
