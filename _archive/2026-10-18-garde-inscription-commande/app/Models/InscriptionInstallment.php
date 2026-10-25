<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Inscription\InstallmentStatus;

final class InscriptionInstallment extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function enrollmentId(): int
    {
        return (int) $this->get('enrollment_id', 0);
    }

    public function label(): string
    {
        return (string) $this->get('label', '');
    }

    public function amountMinor(): int
    {
        return (int) $this->get('amount_minor', 0);
    }

    public function dueDate(): string
    {
        return (string) $this->get('due_date', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', InstallmentStatus::PENDING);
    }

    public function paymentId(): ?int
    {
        $value = $this->get('payment_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function paidAt(): ?string
    {
        $value = $this->get('paid_at');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
