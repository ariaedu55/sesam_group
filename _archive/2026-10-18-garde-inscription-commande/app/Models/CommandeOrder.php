<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Commande\OrderStatus;

final class CommandeOrder extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function clientId(): int
    {
        return (int) $this->get('client_id', 0);
    }

    public function orderNumber(): string
    {
        return (string) $this->get('order_number', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', OrderStatus::NEW);
    }

    public function totalMinor(): int
    {
        return (int) $this->get('total_minor', 0);
    }

    public function depositAmountMinor(): int
    {
        return (int) $this->get('deposit_amount_minor', 0);
    }

    public function paymentId(): ?int
    {
        $value = $this->get('payment_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function fabricNotes(): string
    {
        return (string) $this->get('fabric_notes', '');
    }

    public function instructions(): string
    {
        return (string) $this->get('instructions', '');
    }

    public function photoUrl(): string
    {
        return (string) $this->get('photo_url', '');
    }

    public function deliveryDueAt(): ?string
    {
        $value = $this->get('delivery_due_at');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function deliveredAt(): ?string
    {
        $value = $this->get('delivered_at');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function clientName(): string
    {
        return (string) $this->get('client_name', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
