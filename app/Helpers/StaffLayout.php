<?php

declare(strict_types=1);

namespace Sesam\Helpers;

use Sesam\Core\Auth\Permission;
use Sesam\Models\User;

final class StaffLayout
{
    /**
     * @return array{canManageUsers: bool, canManageTenants: bool, canUseAgency: bool, canViewSettings: bool, canSeeRevenue: bool}
     */
    public static function flags(User $user): array
    {
        return [
            'canManageUsers' => $user->can(Permission::USERS_VIEW),
            'canManageTenants' => $user->can(Permission::TENANTS_VIEW),
            'canUseAgency' => $user->tenantId() === null && $user->can(Permission::AGENCY_VIEW),
            'canViewSettings' => $user->can(Permission::SETTINGS_VIEW),
            'canSeeRevenue' => $user->tenantId() === null && $user->can(Permission::REVENUE_VIEW),
        ];
    }
}
