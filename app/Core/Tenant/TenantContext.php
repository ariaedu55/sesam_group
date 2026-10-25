<?php

declare(strict_types=1);

namespace Sesam\Core\Tenant;

use Sesam\Core\HttpException;
use Sesam\Models\Tenant;

final class TenantContext
{
    private ?Tenant $tenant = null;

    /** @var array<string, mixed> État de l'abonnement du commerce connecté (bandeau, blocage). */
    private array $subscription = [];

    /**
     * @param array<string, mixed> $subscription
     */
    public function setSubscription(array $subscription): void
    {
        $this->subscription = $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    public function subscription(): array
    {
        return $this->subscription;
    }

    public function bind(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id();
    }

    public function requireId(): int
    {
        $id = $this->id();
        if ($id === null) {
            throw new HttpException(403, 'Aucun commerce n\'est associé à cette session.');
        }

        return $id;
    }
}
