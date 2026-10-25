<?php

declare(strict_types=1);

namespace Sesam\Core\Immo;

/**
 * Listes fermées du module Immo (types de biens, de logements, moyens de paiement, interventions).
 */
final class ImmoCatalog
{
    /** @var array<string, string> */
    public const PROPERTY_KINDS = [
        'immeuble' => 'Immeuble',
        'villa' => 'Villa',
        'maison' => 'Maison',
        'residence' => 'Résidence',
        'local' => 'Local commercial',
        'terrain' => 'Terrain',
    ];

    /** @var array<string, string> */
    public const UNIT_KINDS = [
        'appartement' => 'Appartement',
        'studio' => 'Studio',
        'chambre' => 'Chambre',
        'villa' => 'Villa / maison',
        'bureau' => 'Bureau',
        'magasin' => 'Magasin / local',
        'parking' => 'Parking',
    ];

    /** @var array<string, string> */
    public const METHODS = [
        'especes' => 'Espèces',
        'wave' => 'Wave',
        'orange-money' => 'Orange Money',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
        'en-ligne' => 'Paiement en ligne',
    ];

    /** @var array<string, string> Moyens qu'on saisit à la main (le paiement en ligne passe par un lien). */
    public const MANUAL_METHODS = [
        'especes' => 'Espèces',
        'wave' => 'Wave',
        'orange-money' => 'Orange Money',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
    ];

    /** @var array<string, string> */
    public const DUE_STATUSES = [
        'pending' => 'À payer',
        'partial' => 'Payé en partie',
        'paid' => 'Payé',
        'late' => 'En retard',
        'cancelled' => 'Annulé',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        'basse' => 'Peut attendre',
        'normale' => 'Normale',
        'urgente' => 'Urgente',
    ];

    /** @var array<string, string> */
    public const INTERVENTION_STATUSES = [
        'ouverte' => 'À faire',
        'en-cours' => 'En cours',
        'terminee' => 'Terminée',
    ];

    /** @var array<string, string> */
    public const ID_TYPES = [
        'cni' => 'Carte d\'identité',
        'passeport' => 'Passeport',
        'carte-consulaire' => 'Carte consulaire',
        'permis' => 'Permis de conduire',
        'ninea' => 'NINEA (entreprise)',
    ];

    /** @var array<string, string> */
    public const REMINDER_KINDS = [
        'before' => 'Avant l\'échéance',
        'due' => 'Le jour de l\'échéance',
        'late' => 'Retard',
        'manual' => 'Relance manuelle',
    ];

    public const DEFAULT_MESSAGE = 'Bonjour {locataire}, ceci est un rappel pour le loyer de {mois} ({logement}) : {reste} restant à régler, échéance le {echeance}. Merci. {agence}';

    private const MONTHS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public static function monthLabel(string $date): string
    {
        $time = strtotime(substr($date, 0, 10) . ' UTC');
        if ($time === false) {
            return $date;
        }

        return self::MONTHS[(int) gmdate('n', $time)] . ' ' . gmdate('Y', $time);
    }

    public static function date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $time = strtotime(substr($value, 0, 10) . ' UTC');

        return $time === false ? $value : gmdate('d/m/Y', $time);
    }
}
