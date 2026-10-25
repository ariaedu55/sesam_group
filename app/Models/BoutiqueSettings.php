<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Boutique\BoutiqueTheme;

final class BoutiqueSettings extends Model
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

    public function openingHours(): string
    {
        return (string) $this->get('opening_hours', '');
    }

    public function deliveryInfo(): string
    {
        return (string) $this->get('delivery_info', '');
    }

    public function instagramUrl(): string
    {
        return (string) $this->get('instagram_url', '');
    }

    public function facebookUrl(): string
    {
        return (string) $this->get('facebook_url', '');
    }

    public function tiktokUrl(): string
    {
        return (string) $this->get('tiktok_url', '');
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
        $theme = (string) $this->get('theme', BoutiqueTheme::DEFAULT);

        return BoutiqueTheme::isValid($theme) ? $theme : BoutiqueTheme::DEFAULT;
    }

    public function isOpen(): bool
    {
        return (int) $this->get('is_open', 1) === 1;
    }

    public function showStock(): bool
    {
        return (int) $this->get('show_stock', 0) === 1;
    }

    public function updatedAt(): string
    {
        return (string) $this->get('updated_at', '');
    }
}
