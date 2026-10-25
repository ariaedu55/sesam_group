<?php

declare(strict_types=1);

namespace Sesam\Models;

final class ClientNote extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function body(): string
    {
        return (string) $this->get('body', '');
    }

    public function authorName(): string
    {
        return (string) $this->get('author_name', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
