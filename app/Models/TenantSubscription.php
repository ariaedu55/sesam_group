<?php

declare(strict_types=1);

namespace Sesam\Models;

final class TenantSubscription extends Model
{
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    public const TRIAL = 'trial';
    /** Inscription sans essai gratuit : l'espace s'ouvre au premier paiement. */
    public const PENDING = 'pending';

    /** @var array<string, string> */
    public const LABELS = [
        'none' => 'Non configuré',
        'trial' => 'Essai gratuit',
        'trial_over' => 'Essai terminé',
        'pending' => 'Activation en attente',
        'active' => 'Actif',
        'overdue' => 'Échu',
        'suspended' => 'Suspendu',
    ];

    public function tenantId(): int
    {
        return (int) $this->get('tenant_id', 0);
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function monthlyAmount(): ?int
    {
        $amount = $this->get('monthly_amount_minor');

        return $amount === null ? null : (int) $amount;
    }

    public function periodStart(): ?string
    {
        $value = $this->get('period_start');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function periodEnd(): ?string
    {
        $value = $this->get('period_end');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function state(string $today): string
    {
        return self::stateOf($this->status(), $this->periodEnd(), $today);
    }

    public static function stateOf(?string $status, ?string $periodEnd, string $today): string
    {
        if ($status === null || $status === '') {
            return 'none';
        }
        if ($status === self::SUSPENDED) {
            return 'suspended';
        }
        if ($status === self::PENDING) {
            return 'pending';
        }
        if ($status === self::TRIAL) {
            return $periodEnd !== null && $periodEnd < $today ? 'trial_over' : 'trial';
        }

        return $periodEnd !== null && $periodEnd < $today ? 'overdue' : 'active';
    }

    public static function label(string $state): string
    {
        return self::LABELS[$state] ?? $state;
    }
}
