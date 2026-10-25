<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Inscription\EnrollmentStatus;
use Sesam\Models\InscriptionEnrollment;

final class InscriptionEnrollmentRepository extends Repository
{
    public function countToday(int $tenantId, string $dayStart, string $dayEnd): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inscription_enrollments
             WHERE tenant_id = :tenant_id AND created_at >= :start AND created_at < :end'
        );
        $statement->execute(['tenant_id' => $tenantId, 'start' => $dayStart, 'end' => $dayEnd]);

        return (int) $statement->fetchColumn();
    }

    public function countActive(int $tenantId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inscription_enrollments
             WHERE tenant_id = :tenant_id AND status = :status'
        );
        $statement->execute(['tenant_id' => $tenantId, 'status' => EnrollmentStatus::ACTIVE]);

        return (int) $statement->fetchColumn();
    }

    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM inscription_enrollments WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<InscriptionEnrollment>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.class_id, e.student_name, e.guardian_client_id, e.status, e.created_at,
                    c.name AS class_name, g.full_name AS guardian_name
             FROM inscription_enrollments e
             INNER JOIN inscription_classes c ON c.id = e.class_id AND c.tenant_id = e.tenant_id
             INNER JOIN clients g ON g.id = e.guardian_client_id AND g.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tenant_id
             ORDER BY e.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('tenant_id', $tenantId, \PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?InscriptionEnrollment
    {
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.class_id, e.student_name, e.guardian_client_id, e.status, e.created_at,
                    c.name AS class_name, g.full_name AS guardian_name
             FROM inscription_enrollments e
             INNER JOIN inscription_classes c ON c.id = e.class_id AND c.tenant_id = e.tenant_id
             INNER JOIN clients g ON g.id = e.guardian_client_id AND g.tenant_id = e.tenant_id
             WHERE e.id = :id AND e.tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array{class_id: int, student_name: string, guardian_client_id: int, status: string} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO inscription_enrollments (
                tenant_id, class_id, student_name, guardian_client_id, status, created_at, updated_at
             ) VALUES (
                :tenant_id, :class_id, :student_name, :guardian_client_id, :status, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'class_id' => $data['class_id'],
            'student_name' => $data['student_name'],
            'guardian_client_id' => $data['guardian_client_id'],
            'status' => $data['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, int $tenantId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE inscription_enrollments SET status = :status, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): InscriptionEnrollment
    {
        return new InscriptionEnrollment([
            'id' => (int) $row['id'],
            'class_id' => (int) $row['class_id'],
            'student_name' => (string) $row['student_name'],
            'guardian_client_id' => (int) $row['guardian_client_id'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'class_name' => (string) ($row['class_name'] ?? ''),
            'guardian_name' => (string) ($row['guardian_name'] ?? ''),
        ]);
    }

    /**
     * @return list<InscriptionEnrollment>
     */
    private function mapList(\PDOStatement $statement): array
    {
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->map($row);
            }
        }

        return $items;
    }
}
