<?php

declare(strict_types=1);

namespace Sesam\Models;

final class MessageTemplate extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function slug(): string
    {
        return (string) $this->get('slug', '');
    }

    public function channel(): string
    {
        return (string) $this->get('channel', '');
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function subject(): ?string
    {
        $value = $this->get('subject');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function body(): string
    {
        return (string) $this->get('body', '');
    }
}
