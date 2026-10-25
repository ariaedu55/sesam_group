<?php

declare(strict_types=1);

namespace Sesam\Models;

final class CommandeOrderItem extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function productName(): string
    {
        return (string) $this->get('product_name', '');
    }

    public function quantity(): int
    {
        return (int) $this->get('quantity', 0);
    }

    public function unitPriceMinor(): int
    {
        return (int) $this->get('unit_price_minor', 0);
    }

    public function lineTotalMinor(): int
    {
        return (int) $this->get('line_total_minor', 0);
    }
}
