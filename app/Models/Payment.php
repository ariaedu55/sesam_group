<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Payment\PaymentStatus;

final class Payment extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function tenantId(): int
    {
        return (int) $this->get('tenant_id', 0);
    }

    public function clientId(): ?int
    {
        $value = $this->get('client_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function reference(): string
    {
        return (string) $this->get('reference', '');
    }

    public function amountMinor(): int
    {
        return (int) $this->get('amount_minor', 0);
    }

    public function currency(): string
    {
        return (string) $this->get('currency', 'XOF');
    }

    public function status(): string
    {
        return (string) $this->get('status', PaymentStatus::PENDING);
    }

    public function gateway(): string
    {
        return (string) $this->get('gateway', 'naboopay');
    }

    public function mode(): string
    {
        return (string) $this->get('mode', 'sandbox');
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function externalId(): ?string
    {
        $value = $this->get('external_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function paidAt(): ?string
    {
        $value = $this->get('paid_at');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    public function clientName(): string
    {
        return (string) $this->get('client_name', '');
    }
}
