<?php

declare(strict_types=1);

namespace Sesam\Models;

final class ReservationPrestation extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function category(): string
    {
        return (string) $this->get('category', '');
    }

    public function durationMinutes(): int
    {
        return (int) $this->get('duration_minutes', 0);
    }

    public function priceMinor(): int
    {
        return (int) $this->get('price_minor', 0);
    }

    public function depositMinor(): int
    {
        return (int) $this->get('deposit_minor', 0);
    }

    public function imagePath(): ?string
    {
        $path = $this->get('image_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Version de la photo pour l'adresse publique (le navigateur recharge quand elle change). */
    public function imageVersion(): string
    {
        return substr(md5((string) $this->get('image_path', '') . (string) $this->get('updated_at', '')), 0, 10);
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }

    /** « 1 h 30 », « 45 min ». */
    public function durationLabel(): string
    {
        $minutes = $this->durationMinutes();
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        if ($hours === 0) {
            return $rest . ' min';
        }

        return $hours . ' h' . ($rest > 0 ? ' ' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT) : '');
    }
}
