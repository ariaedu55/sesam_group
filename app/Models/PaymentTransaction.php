<?php

declare(strict_types=1);

namespace Sesam\Models;

final class PaymentTransaction extends Model
{
    public function kind(): string
    {
        return (string) $this->get('kind', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    public function payload(): string
    {
        return (string) $this->get('payload', '');
    }
}
