<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Garde\ExpiryStatus;

final class GardeBatch extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function productId(): int
    {
        return (int) $this->get('product_id', 0);
    }

    public function productName(): string
    {
        return (string) $this->get('product_name', '');
    }

    public function lotCode(): string
    {
        return (string) $this->get('lot_code', '');
    }

    public function quantity(): int
    {
        return (int) $this->get('quantity', 0);
    }

    public function expiresOn(): string
    {
        return (string) $this->get('expires_on', '');
    }

    public function expiryStatus(): string
    {
        return ExpiryStatus::of($this->expiresOn());
    }
}
