<?php

declare(strict_types=1);

namespace Sesam\Core\Push;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Tenant\Sector;

/**
 * Catégories de notifications : à qui elles s'adressent (module du commerce, ou personnel SESAM)
 * et quelle permission il faut pour les recevoir. Chaque utilisateur peut couper une catégorie.
 */
final class NotificationCatalog
{
    /**
     * @var array<string, array{label: string, hint: string, sector: ?string, permission: string, staff: bool}>
     */
    public const CATEGORIES = [
        // Réservation
        'reservation.new' => ['label' => 'Nouvelles réservations', 'hint' => 'Une cliente réserve en ligne', 'sector' => Sector::RESERVATION, 'permission' => Permission::RESERVATION_VIEW, 'staff' => false],
        'reservation.pending' => ['label' => 'Réservations à confirmer', 'hint' => 'Chaque matin, les demandes en attente de votre réponse', 'sector' => Sector::RESERVATION, 'permission' => Permission::RESERVATION_APPOINTMENTS, 'staff' => false],
        'reservation.today' => ['label' => 'Rendez-vous du jour', 'hint' => 'Le programme de la journée, chaque matin', 'sector' => Sector::RESERVATION, 'permission' => Permission::RESERVATION_VIEW, 'staff' => false],
        'reservation.deposit' => ['label' => 'Acomptes payés', 'hint' => 'Une cliente a réglé son acompte', 'sector' => Sector::RESERVATION, 'permission' => Permission::RESERVATION_VIEW, 'staff' => false],
        // Boutique
        'boutique.order' => ['label' => 'Nouvelles commandes', 'hint' => 'Une commande arrive de la boutique en ligne', 'sector' => Sector::BOUTIQUE, 'permission' => Permission::BOUTIQUE_VIEW, 'staff' => false],
        'boutique.pending' => ['label' => 'Commandes à traiter', 'hint' => 'Chaque matin, les commandes encore en attente', 'sector' => Sector::BOUTIQUE, 'permission' => Permission::BOUTIQUE_ORDERS, 'staff' => false],
        'boutique.stock' => ['label' => 'Stock bas', 'hint' => 'Un produit est bientôt épuisé ou épuisé, et le point du lundi', 'sector' => Sector::BOUTIQUE, 'permission' => Permission::BOUTIQUE_VIEW, 'staff' => false],
        // Immo
        'immo.payment' => ['label' => 'Loyers payés', 'hint' => 'Un locataire a payé en ligne', 'sector' => Sector::LOYER, 'permission' => Permission::LOYER_VIEW, 'staff' => false],
        'immo.due_soon' => ['label' => 'Échéances proches', 'hint' => 'Des loyers arrivent à échéance dans 3 jours', 'sector' => Sector::LOYER, 'permission' => Permission::LOYER_VIEW, 'staff' => false],
        'immo.late' => ['label' => 'Loyers en retard', 'hint' => 'Un loyer n\'est pas payé après le délai de grâce', 'sector' => Sector::LOYER, 'permission' => Permission::LOYER_VIEW, 'staff' => false],
        'immo.lease_end' => ['label' => 'Fins de contrat de bail', 'hint' => 'Un contrat se termine bientôt', 'sector' => Sector::LOYER, 'permission' => Permission::LOYER_LEASES, 'staff' => false],
        'immo.intervention' => ['label' => 'Interventions urgentes', 'hint' => 'Une intervention urgente est signalée', 'sector' => Sector::LOYER, 'permission' => Permission::LOYER_VIEW, 'staff' => false],
        // Tous les commerces
        'payment.received' => ['label' => 'Paiements en ligne reçus', 'hint' => 'Un paiement Wave / Orange Money est confirmé', 'sector' => null, 'permission' => Permission::PAYMENTS_VIEW, 'staff' => false],
        'subscription' => ['label' => 'Mon abonnement SESAM', 'hint' => 'Fin d\'essai, échéance, paiement confirmé', 'sector' => null, 'permission' => Permission::SUBSCRIPTION_PAY, 'staff' => false],
        // Console SESAM
        'sesam.signup' => ['label' => 'Nouvelles inscriptions', 'hint' => 'Un commerce s\'inscrit depuis le site', 'sector' => null, 'permission' => Permission::TENANTS_VIEW, 'staff' => true],
        'sesam.quote' => ['label' => 'Demandes de devis site web', 'hint' => 'Un projet de site arrive du formulaire, et les demandes pas encore traitées', 'sector' => null, 'permission' => Permission::AGENCY_VIEW, 'staff' => true],
        'sesam.payment' => ['label' => 'Abonnements payés', 'hint' => 'Un commerce paie en ligne, ou un paiement est encaissé dans la console', 'sector' => null, 'permission' => Permission::REVENUE_VIEW, 'staff' => true],
        'sesam.trial' => ['label' => 'Essais qui se terminent', 'hint' => 'Des essais finissent dans 3 jours : à convertir', 'sector' => null, 'permission' => Permission::TENANTS_VIEW, 'staff' => true],
        'sesam.overdue' => ['label' => 'Abonnements échus', 'hint' => 'Le lendemain de l\'échéance, puis chaque lundi tant que ce n\'est pas payé', 'sector' => null, 'permission' => Permission::REVENUE_VIEW, 'staff' => true],
        'sesam.lost' => ['label' => 'Essais terminés sans abonnement', 'hint' => 'Des commerces viennent d\'être bloqués : à rappeler', 'sector' => null, 'permission' => Permission::TENANTS_VIEW, 'staff' => true],
        'sesam.agency' => ['label' => 'Suivi des prospects', 'hint' => 'Relances prévues aujourd\'hui et devis sans réponse', 'sector' => null, 'permission' => Permission::AGENCY_PROSPECTS, 'staff' => true],
        'sesam.invoices' => ['label' => 'Factures agence en retard', 'hint' => 'Des factures clients ont dépassé leur échéance', 'sector' => null, 'permission' => Permission::AGENCY_INVOICES, 'staff' => true],
        'sesam.security' => ['label' => 'Sécurité', 'hint' => 'Nouveau compte de l\'équipe, connexions bloquées', 'sector' => null, 'permission' => Permission::USERS_VIEW, 'staff' => true],
        // Pour tous
        'general' => ['label' => 'Messages généraux', 'hint' => 'Notifications de test et informations', 'sector' => null, 'permission' => '', 'staff' => false],
    ];

    /**
     * Catégories proposées à un utilisateur : celles de son espace et de ses droits.
     *
     * @param list<string> $permissions
     * @return array<string, array{label: string, hint: string, sector: ?string, permission: string, staff: bool}>
     */
    public static function forUser(bool $isStaff, ?string $sector, array $permissions): array
    {
        return array_filter(self::CATEGORIES, static function (array $category, string $key) use ($isStaff, $sector, $permissions): bool {
            if ($key === 'general') {
                return true;
            }
            if ($category['staff'] !== $isStaff) {
                return false;
            }
            if ($category['sector'] !== null && $category['sector'] !== $sector) {
                return false;
            }

            return $category['permission'] === '' || in_array($category['permission'], $permissions, true);
        }, ARRAY_FILTER_USE_BOTH);
    }
}
