<?php

declare(strict_types=1);

namespace Sesam\Models;

final class Notification extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function title(): string
    {
        return (string) $this->get('title', '');
    }

    public function body(): string
    {
        return (string) $this->get('body', '');
    }

    public function category(): string
    {
        return (string) $this->get('category', 'general');
    }

    public function level(): string
    {
        return (string) $this->get('level', 'info');
    }

    public function link(): ?string
    {
        $link = $this->get('link');

        return is_string($link) && $link !== '' ? $link : null;
    }

    public function isRead(): bool
    {
        return $this->get('read_at') !== null && $this->get('read_at') !== '';
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
