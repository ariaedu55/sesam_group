<?php

declare(strict_types=1);

namespace Sesam\Models;

final class BoutiqueOrder extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function clientId(): ?int
    {
        $value = $this->get('client_id');

        return $value === null ? null : (int) $value;
    }

    public function orderNumber(): string
    {
        return (string) $this->get('order_number', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', 'new');
    }

    public function source(): string
    {
        return (string) $this->get('source', 'whatsapp');
    }

    public function customerName(): string
    {
        return (string) $this->get('customer_name', '');
    }

    public function customerPhone(): string
    {
        return (string) $this->get('customer_phone', '');
    }

    public function customerAddress(): string
    {
        return (string) $this->get('customer_address', '');
    }

    public function note(): string
    {
        return (string) $this->get('note', '');
    }

    public function totalMinor(): int
    {
        return (int) $this->get('total_minor', 0);
    }

    public function isPaid(): bool
    {
        return (int) $this->get('is_paid', 0) === 1;
    }

    public function stockApplied(): bool
    {
        return (int) $this->get('stock_applied', 0) === 1;
    }

    public function itemCount(): int
    {
        return (int) $this->get('item_count', 0);
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    public function confirmedAt(): ?string
    {
        $value = $this->get('confirmed_at');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function deliveredAt(): ?string
    {
        $value = $this->get('delivered_at');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
