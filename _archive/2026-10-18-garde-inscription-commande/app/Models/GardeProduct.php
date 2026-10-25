<?php

declare(strict_types=1);

namespace Sesam\Models;

final class GardeProduct extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function unit(): string
    {
        return (string) $this->get('unit', 'unité');
    }

    public function minQuantity(): int
    {
        return (int) $this->get('min_quantity', 0);
    }

    public function imagePath(): string
    {
        return (string) $this->get('image_path', '');
    }

    public function hasImage(): bool
    {
        return $this->imagePath() !== '';
    }

    public function stockQuantity(): int
    {
        return (int) $this->get('stock_quantity', 0);
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }
}
