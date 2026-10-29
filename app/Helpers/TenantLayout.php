<?php

declare(strict_types=1);

namespace Sesam\Helpers;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Tenant\Sector;
use Sesam\Models\Tenant;
use Sesam\Models\User;

final class TenantLayout
{
    /**
     * @return array{tenantDisplayName: string, tenantSectorLabel: string, showTeamNav: bool, showClientsNav: bool, showPaymentsNav: bool, showStatsNav: bool, showReservationNav: bool, showBoutiqueNav: bool, showLoyerNav: bool}
     */
    public static function meta(?Tenant $tenant, User $user): array
    {
        $reservationSector = $tenant instanceof Tenant && $tenant->sector() === Sector::RESERVATION;
        $boutiqueSector = $tenant instanceof Tenant && $tenant->sector() === Sector::BOUTIQUE;
        $loyerSector = $tenant instanceof Tenant && $tenant->sector() === Sector::LOYER;

        return [
            'tenantDisplayName' => $tenant instanceof Tenant ? $tenant->displayName() : '',
            'tenantSectorLabel' => $tenant instanceof Tenant ? $tenant->sectorLabel() : '',
            'showTeamNav' => $user->can(Permission::USERS_VIEW),
            // Immo a sa propre page Locataires.
            'showClientsNav' => !$loyerSector && $user->can(Permission::CLIENTS_VIEW),
            // Une boutique encaisse via WhatsApp ou en main propre : le suivi se fait sur la commande (« Payée »).
            // Un salon suit ses acomptes sur chaque rendez-vous et sur l'accueil : pas de liste de paiements à part.
            'showPaymentsNav' => !$boutiqueSector && !$loyerSector && !$reservationSector && $user->can(Permission::PAYMENTS_VIEW),
            'showStatsNav' => $tenant instanceof Tenant && $user->can(Permission::STATS_VIEW),
            'showReservationNav' => $reservationSector && $user->can(Permission::RESERVATION_VIEW),
            'showBoutiqueNav' => $boutiqueSector && $user->can(Permission::BOUTIQUE_VIEW),
            'showLoyerNav' => $loyerSector && $user->can(Permission::LOYER_VIEW),
            'showImmoSettingsNav' => $loyerSector && $user->can(Permission::LOYER_SETTINGS),
        ];
    }
}
