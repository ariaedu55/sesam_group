<?php

declare(strict_types=1);

namespace Sesam\Models;

final class GardeMovement extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function productId(): int
    {
        return (int) $this->get('product_id', 0);
    }

    public function productName(): string
    {
        return (string) $this->get('product_name', '');
    }

    public function lotCode(): string
    {
        return (string) $this->get('lot_code', '');
    }

    public function type(): string
    {
        return (string) $this->get('type', '');
    }

    public function quantity(): int
    {
        return (int) $this->get('quantity', 0);
    }

    public function reason(): string
    {
        return (string) $this->get('reason', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
