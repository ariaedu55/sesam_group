<?php

declare(strict_types=1);

namespace Sesam\Models;

final class InscriptionClass extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }
}
