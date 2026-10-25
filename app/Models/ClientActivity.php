<?php

declare(strict_types=1);

namespace Sesam\Models;

final class ClientActivity extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function clientId(): int
    {
        return (int) $this->get('client_id', 0);
    }

    public function kind(): string
    {
        return (string) $this->get('kind', '');
    }

    public function summary(): string
    {
        return (string) $this->get('summary', '');
    }

    public function actorName(): string
    {
        return (string) $this->get('actor_name', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
