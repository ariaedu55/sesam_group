<?php

declare(strict_types=1);

namespace Sesam\Models;

final class LoyerUnit extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function propertyId(): int
    {
        return (int) $this->get('property_id', 0);
    }

    public function label(): string
    {
        return (string) $this->get('label', '');
    }

    public function defaultRentMinor(): int
    {
        return (int) $this->get('default_rent_minor', 0);
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }
}
