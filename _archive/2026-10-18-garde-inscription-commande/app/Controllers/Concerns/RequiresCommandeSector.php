<?php

declare(strict_types=1);

namespace Sesam\Controllers\Concerns;

use Sesam\Core\HttpException;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Models\Tenant;

trait RequiresCommandeSector
{
    protected function assertCommandeSector(TenantContext $context): void
    {
        $tenant = $context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::COMMANDE) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Commande.');
        }
    }
}
