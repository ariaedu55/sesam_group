<?php

declare(strict_types=1);

namespace Sesam\Models;

final class User extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function fullName(): string
    {
        return (string) $this->get('full_name', '');
    }

    public function email(): string
    {
        return (string) $this->get('email', '');
    }

    public function tenantId(): ?int
    {
        $value = $this->get('tenant_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function passwordHash(): string
    {
        return (string) $this->get('password_hash', '');
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        $roles = $this->get('roles', []);

        return is_array($roles) ? array_values(array_map('strval', $roles)) : [];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $permissions = $this->get('permissions', []);

        return is_array($permissions) ? array_values(array_map('strval', $permissions)) : [];
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roles(), true);
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
