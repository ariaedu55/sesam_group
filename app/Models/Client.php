<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Crm\ClientStatus;

final class Client extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function tenantId(): int
    {
        return (int) $this->get('tenant_id', 0);
    }

    public function fullName(): string
    {
        return (string) $this->get('full_name', '');
    }

    public function email(): ?string
    {
        $value = $this->get('email');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function phone(): ?string
    {
        $value = $this->get('phone');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function status(): string
    {
        return (string) $this->get('status', ClientStatus::ACTIVE);
    }

    public function isArchived(): bool
    {
        return $this->status() === ClientStatus::ARCHIVED;
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    public function updatedAt(): string
    {
        return (string) $this->get('updated_at', '');
    }

    /**
     * @return list<ClientTag>
     */
    public function tags(): array
    {
        $tags = $this->get('tags', []);

        return is_array($tags) ? array_values(array_filter($tags, static fn ($tag) => $tag instanceof ClientTag)) : [];
    }
}
