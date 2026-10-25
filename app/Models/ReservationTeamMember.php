<?php

declare(strict_types=1);

namespace Sesam\Models;

final class ReservationTeamMember extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function name(): string
    {
        return (string) $this->get('name', '');
    }

    public function roleLabel(): string
    {
        return (string) $this->get('role_label', '');
    }

    public function phone(): string
    {
        return (string) $this->get('phone', '');
    }

    public function isActive(): bool
    {
        return (int) $this->get('is_active', 0) === 1;
    }

    /**
     * Prestations que cette personne réalise. Liste vide : elle les fait toutes.
     *
     * @return list<int>
     */
    public function serviceIds(): array
    {
        $ids = $this->get('service_ids', []);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function does(int $serviceId): bool
    {
        $ids = $this->serviceIds();

        return $ids === [] || in_array($serviceId, $ids, true);
    }
}
