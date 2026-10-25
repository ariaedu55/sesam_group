<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Core\Inscription\EnrollmentStatus;

final class InscriptionEnrollment extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function classId(): int
    {
        return (int) $this->get('class_id', 0);
    }

    public function studentName(): string
    {
        return (string) $this->get('student_name', '');
    }

    public function guardianClientId(): int
    {
        return (int) $this->get('guardian_client_id', 0);
    }

    public function status(): string
    {
        return (string) $this->get('status', EnrollmentStatus::ACTIVE);
    }

    public function className(): string
    {
        return (string) $this->get('class_name', '');
    }

    public function guardianName(): string
    {
        return (string) $this->get('guardian_name', '');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
