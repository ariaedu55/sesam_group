<?php

declare(strict_types=1);

namespace Sesam\Core\Validation;

use Sesam\Core\HttpException;

final class ValidationException extends HttpException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(422, 'Les informations envoyées sont incomplètes ou invalides.');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
