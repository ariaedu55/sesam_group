<?php

declare(strict_types=1);

namespace Sesam\Models;

final class LoyerProperty extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function address(): string
    {
        return (string) $this->get('address', '');
    }

    public function city(): string
    {
        return (string) $this->get('city', '');
    }

    public function kind(): string
    {
        return (string) $this->get('kind', 'immeuble');
    }

    public function imagePath(): ?string
    {
        $path = $this->get('image_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function imageVersion(): string
    {
        return rawurlencode((string) $this->get('updated_at', ''));
    }

    public function location(): string
    {
        $address = $this->address();
        $city = $this->city();
        if ($city === '' || ($address !== '' && mb_stripos($address, $city) !== false)) {
            return $address !== '' ? $address : $city;
        }

        return trim($address . ', ' . $city, ', ');
    }
}
