<?php

declare(strict_types=1);

namespace Sesam\Models;

final class BoutiqueProduct extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function category(): string
    {
        return (string) $this->get('category', '');
    }

    public function priceMinor(): int
    {
        return (int) $this->get('price_minor', 0);
    }

    /** Ancien prix barré, affiché seulement s'il dépasse le prix actuel. */
    public function comparePriceMinor(): ?int
    {
        $value = $this->get('compare_price_minor');
        if ($value === null || (int) $value <= $this->priceMinor()) {
            return null;
        }

        return (int) $value;
    }

    public function tracksStock(): bool
    {
        return (int) $this->get('track_stock', 1) === 1;
    }

    public function stockQuantity(): int
    {
        return (int) $this->get('stock_quantity', 0);
    }

    public function lowStockThreshold(): int
    {
        return (int) $this->get('low_stock_threshold', 3);
    }

    public function imagePath(): ?string
    {
        $value = $this->get('image_path');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isPublished(): bool
    {
        return (int) $this->get('is_published', 1) === 1;
    }

    public function isAvailable(): bool
    {
        return !$this->tracksStock() || $this->stockQuantity() > 0;
    }

    public function isLowStock(): bool
    {
        return $this->tracksStock() && $this->stockQuantity() > 0 && $this->stockQuantity() <= $this->lowStockThreshold();
    }

    public function isOutOfStock(): bool
    {
        return $this->tracksStock() && $this->stockQuantity() <= 0;
    }

    public function updatedAt(): string
    {
        return (string) $this->get('updated_at', '');
    }
}
