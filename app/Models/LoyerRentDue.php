<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Loyer\RentDueStatus;

final class LoyerRentDue extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function leaseId(): int
    {
        return (int) $this->get('lease_id', 0);
    }

    public function dueDate(): string
    {
        return (string) $this->get('due_date', '');
    }

    public function amountMinor(): int
    {
        return (int) $this->get('amount_minor', 0);
    }

    public function status(): string
    {
        return (string) $this->get('status', RentDueStatus::PENDING);
    }

    public function paymentId(): ?int
    {
        $value = $this->get('payment_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function clientName(): string
    {
        return (string) $this->get('client_name', '');
    }

    public function unitLabel(): string
    {
        return (string) $this->get('unit_label', '');
    }

    public function propertyName(): string
    {
        return (string) $this->get('property_name', '');
    }

    public function isLate(): bool
    {
        if ($this->status() !== RentDueStatus::PENDING) {
            return false;
        }
        $due = strtotime($this->dueDate() . ' 23:59:59 UTC');

        return $due !== false && $due < time();
    }
}
