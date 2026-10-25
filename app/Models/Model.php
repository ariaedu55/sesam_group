<?php

declare(strict_types=1);

namespace Sesam\Models;

class Model
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private readonly array $attributes = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $merge
     */
    public function with(array $merge): static
    {
        return new static([...$this->attributes, ...$merge]);
    }
}
