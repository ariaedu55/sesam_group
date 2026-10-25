<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Reservation\AppointmentStatus;

final class ReservationAppointment extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function clientId(): int
    {
        return (int) $this->get('client_id', 0);
    }

    public function serviceId(): int
    {
        return (int) $this->get('service_id', 0);
    }

    public function staffId(): ?int
    {
        $value = $this->get('staff_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function startsAt(): string
    {
        return (string) $this->get('starts_at', '');
    }

    public function durationMinutes(): int
    {
        return (int) $this->get('duration_minutes', 0);
    }

    public function status(): string
    {
        return (string) $this->get('status', AppointmentStatus::PENDING);
    }

    public function depositAmountMinor(): int
    {
        return (int) $this->get('deposit_amount_minor', 0);
    }

    public function paymentId(): ?int
    {
        $value = $this->get('payment_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function clientName(): string
    {
        return (string) $this->get('client_name', '');
    }

    public function serviceName(): string
    {
        return (string) $this->get('service_name', '');
    }

    public function staffName(): string
    {
        return (string) $this->get('staff_name', '');
    }

    public function clientPhone(): string
    {
        return (string) $this->get('client_phone', '');
    }

    public function priceMinor(): int
    {
        return (int) $this->get('price_minor', 0);
    }

    public function isPaid(): bool
    {
        return (int) $this->get('is_paid', 0) === 1;
    }

    public function source(): string
    {
        return (string) $this->get('source', 'manual');
    }

    public function notes(): string
    {
        return (string) $this->get('notes', '');
    }

    public function endsAt(): string
    {
        $start = strtotime($this->startsAt());

        return $start === false ? $this->startsAt() : gmdate('Y-m-d H:i:s', $start + $this->durationMinutes() * 60);
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
