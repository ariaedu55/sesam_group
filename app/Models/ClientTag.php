<?php

declare(strict_types=1);

namespace Sesam\Models;

final class ClientTag extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function tenantId(): int
    {
        return (int) $this->get('tenant_id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }
}
