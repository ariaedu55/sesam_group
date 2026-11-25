<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Tenant\Sector;

final class Tenant extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function slug(): string
    {
        return (string) $this->get('slug', '');
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function displayName(): string
    {
        $display = (string) $this->get('display_name', '');

        return $display !== '' ? $display : $this->name();
    }

    public function sector(): string
    {
        return (string) $this->get('sector', '');
    }

    public function sectorLabel(): string
    {
        return Sector::label($this->sector());
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function phone(): string
    {
        return (string) $this->get('phone', '');
    }

    /** Code pays ISO (SN, US, FR…). */
    public function country(): string
    {
        return (string) $this->get('country', \Sesam\Core\Money\Country::DEFAULT);
    }

    public function countryName(): string
    {
        return \Sesam\Core\Money\Country::name($this->country());
    }

    /** Devise de l'abonnement SESAM du commerce (XOF, USD, EUR). */
    public function currency(): string
    {
        return \Sesam\Core\Money\Currency::normalize((string) $this->get('currency', ''));
    }

    /** « site » : inscrit seul depuis la page Commencer ; « console » : créé par SESAM. */
    public function signupSource(): string
    {
        return (string) $this->get('signup_source', 'console');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    public function isActive(): bool
    {
        return $this->status() === 'active';
    }
}
