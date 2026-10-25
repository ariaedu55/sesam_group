<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Services\Agency\AgencyCatalog;

final class AgencyInvoice extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function projectId(): int
    {
        return (int) $this->get('project_id', 0);
    }

    public function reference(): string
    {
        return (string) $this->get('reference', '');
    }

    public function label(): string
    {
        return (string) $this->get('label', '');
    }

    public function amount(): int
    {
        return (int) $this->get('amount', 0);
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function statusLabel(): string
    {
        return AgencyCatalog::INVOICE_STATUSES[$this->status()] ?? $this->status();
    }

    public function isPaid(): bool
    {
        return $this->status() === 'paid';
    }

    public function dueOn(): string
    {
        return (string) $this->get('due_on', '');
    }

    public function paidOn(): ?string
    {
        $value = $this->get('paid_on');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function paymentMethodLabel(): string
    {
        $method = (string) $this->get('payment_method', '');

        return AgencyCatalog::PAYMENT_METHODS[$method] ?? '';
    }

    public function isOverdue(string $today): bool
    {
        return !$this->isPaid() && $this->dueOn() < $today;
    }

    public function projectTitle(): string
    {
        return (string) $this->get('project_title', '');
    }
}
