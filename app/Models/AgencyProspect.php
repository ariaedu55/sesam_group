<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Services\Agency\AgencyCatalog;

final class AgencyProspect extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function fullName(): string
    {
        return (string) $this->get('full_name', '');
    }

    public function businessName(): string
    {
        return (string) $this->get('business_name', '');
    }

    public function displayName(): string
    {
        return $this->businessName() !== '' ? $this->businessName() : $this->fullName();
    }

    public function email(): string
    {
        return (string) $this->get('email', '');
    }

    public function phone(): string
    {
        return (string) $this->get('phone', '');
    }

    public function activity(): string
    {
        return (string) $this->get('activity', '');
    }

    public function activityLabel(): string
    {
        return AgencyCatalog::activities()[$this->activity()] ?? $this->activity();
    }

    public function source(): string
    {
        return (string) $this->get('source', '');
    }

    public function stage(): string
    {
        return (string) $this->get('stage', '');
    }

    public function stageLabel(): string
    {
        return AgencyCatalog::STAGES[$this->stage()] ?? $this->stage();
    }

    public function isClient(): bool
    {
        return in_array($this->stage(), AgencyCatalog::CLIENT_STAGES, true);
    }

    public function nextActionOn(): ?string
    {
        $value = $this->get('next_action_on');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function updatedAt(): string
    {
        return (string) $this->get('updated_at', '');
    }
}
