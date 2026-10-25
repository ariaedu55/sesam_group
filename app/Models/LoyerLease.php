<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Loyer\LeaseStatus;

final class LoyerLease extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function unitId(): int
    {
        return (int) $this->get('unit_id', 0);
    }

    public function clientId(): int
    {
        return (int) $this->get('client_id', 0);
    }

    public function rentMinor(): int
    {
        return (int) $this->get('rent_minor', 0);
    }

    public function dueDay(): int
    {
        return (int) $this->get('due_day', 5);
    }

    public function startsOn(): string
    {
        return (string) $this->get('starts_on', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', LeaseStatus::ACTIVE);
    }

    public function clientName(): string
    {
        return (string) $this->get('client_name', '');
    }

    public function unitLabel(): string
    {
        return (string) $this->get('unit_label', '');
    }

    public function propertyName(): string
    {
        return (string) $this->get('property_name', '');
    }
}
