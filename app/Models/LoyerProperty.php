<?php

declare(strict_types=1);

namespace Sesam\Models;

final class LoyerProperty extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function address(): string
    {
        return (string) $this->get('address', '');
    }
}
