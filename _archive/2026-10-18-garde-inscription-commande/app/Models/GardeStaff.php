<?php

declare(strict_types=1);

namespace Sesam\Models;

final class GardeStaff extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function roleLabel(): string
    {
        return (string) $this->get('role_label', '');
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }
}
