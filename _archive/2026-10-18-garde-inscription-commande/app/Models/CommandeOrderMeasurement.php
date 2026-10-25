<?php

declare(strict_types=1);

namespace Sesam\Models;

final class CommandeOrderMeasurement extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function chestCm(): ?string
    {
        return $this->formatDecimal('chest_cm');
    }

    public function waistCm(): ?string
    {
        return $this->formatDecimal('waist_cm');
    }

    public function hipsCm(): ?string
    {
        return $this->formatDecimal('hips_cm');
    }

    public function lengthCm(): ?string
    {
        return $this->formatDecimal('length_cm');
    }

    public function notes(): string
    {
        return (string) $this->get('notes', '');
    }

    public function authorName(): string
    {
        return (string) $this->get('author_name', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    private function formatDecimal(string $key): ?string
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
