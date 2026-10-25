<?php

declare(strict_types=1);

namespace Sesam\Core;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $publicMessage,
    ) {
        parent::__construct($publicMessage);
    }

    public function status(): int
    {
        return $this->status;
    }
}
