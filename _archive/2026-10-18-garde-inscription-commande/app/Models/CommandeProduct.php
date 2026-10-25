<?php

declare(strict_types=1);

namespace Sesam\Models;

final class CommandeProduct extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function priceMinor(): int
    {
        return (int) $this->get('price_minor', 0);
    }

    public function depositMinor(): int
    {
        return (int) $this->get('deposit_minor', 0);
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }
}
