<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Boutique\BoutiqueTheme;

final class ReservationSettings extends Model
{
    public function whatsappPhone(): string
    {
        return (string) $this->get('whatsapp_phone', '');
    }

    public function tagline(): string
    {
        return (string) $this->get('tagline', '');
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function address(): string
    {
        return (string) $this->get('address', '');
    }

    public function logoPath(): ?string
    {
        $value = $this->get('logo_path');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function coverPath(): ?string
    {
        $value = $this->get('cover_path');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function theme(): string
    {
        $theme = (string) $this->get('theme', 'ocean');

        return BoutiqueTheme::isValid($theme) ? $theme : 'ocean';
    }

    public function isOpen(): bool
    {
        return (int) $this->get('is_open', 1) === 1;
    }

    public function autoConfirm(): bool
    {
        return (int) $this->get('auto_confirm', 0) === 1;
    }

    public function slotStepMinutes(): int
    {
        return max(5, (int) $this->get('slot_step_minutes', 30));
    }

    public function minNoticeHours(): int
    {
        return (int) $this->get('min_notice_hours', 2);
    }

    public function maxAdvanceDays(): int
    {
        return max(1, (int) $this->get('max_advance_days', 60));
    }

    public function bufferMinutes(): int
    {
        return (int) $this->get('buffer_minutes', 0);
    }

    public function cancellationPolicy(): string
    {
        return (string) $this->get('cancellation_policy', '');
    }

    public function updatedAt(): string
    {
        return (string) $this->get('updated_at', '');
    }

    /**
     * Horaires d'ouverture : weekday (1 = lundi) => liste de plages [ouverture, fermeture] « HH:MM:SS ».
     * Tableau vide : le salon n'a pas encore réglé ses horaires.
     *
     * @return array<int, list<array{0: string, 1: string}>>
     */
    public function hours(): array
    {
        $hours = $this->get('hours', []);

        return is_array($hours) ? $hours : [];
    }
}
