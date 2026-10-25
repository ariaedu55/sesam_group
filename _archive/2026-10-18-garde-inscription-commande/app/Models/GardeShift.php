<?php

declare(strict_types=1);

namespace Sesam\Models;

final class GardeShift extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function staffId(): int
    {
        return (int) $this->get('staff_id', 0);
    }

    public function staffName(): string
    {
        return (string) $this->get('staff_name', '');
    }

    public function shiftDate(): string
    {
        return (string) $this->get('shift_date', '');
    }

    public function note(): string
    {
        return (string) $this->get('note', '');
    }
}
