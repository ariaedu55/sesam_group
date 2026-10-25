<?php

declare(strict_types=1);

namespace Sesam\Models;

final class BoutiqueStockMovement extends Model
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

    public function type(): string
    {
        return (string) $this->get('type', '');
    }

    public function quantity(): int
    {
        return (int) $this->get('quantity', 0);
    }

    public function stockAfter(): int
    {
        return (int) $this->get('stock_after', 0);
    }

    public function note(): string
    {
        return (string) $this->get('note', '');
    }

    public function orderId(): ?int
    {
        $value = $this->get('order_id');

        return $value === null ? null : (int) $value;
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
