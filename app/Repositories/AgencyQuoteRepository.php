<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\AgencyQuote;

final class AgencyQuoteRepository extends Repository
{
    private const COLUMNS = 'id, prospect_id, reference, offer, site_path, amount, description, status, sent_at, decided_at, created_at, updated_at';

    /**
     * @return list<AgencyQuote>
     */
    public function forProspect(int $prospectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM agency_quotes WHERE prospect_id = :prospect_id ORDER BY id DESC'
        );
        $statement->execute(['prospect_id' => $prospectId]);
        $list = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $list[] = new AgencyQuote($row);
            }
        }

        return $list;
    }

    public function find(int $id): ?AgencyQuote
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM agency_quotes WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? new AgencyQuote($row) : null;
    }

    /**
     * @param array{prospect_id: int, offer: string, site_path: string, amount: int, description: string} $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO agency_quotes (prospect_id, reference, offer, site_path, amount, description, status, created_at, updated_at)
             VALUES (:prospect_id, :reference, :offer, :site_path, :amount, :description, :status, :created_at, :updated_at)'
        );
        $statement->execute($data + [
            'reference' => 'tmp-' . bin2hex(random_bytes(6)),
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE agency_quotes SET reference = :reference WHERE id = :id')->execute([
            'id' => $id,
            'reference' => sprintf('DV-%s-%04d', gmdate('Y'), $id),
        ]);

        return $id;
    }

    public function updateStatus(int $id, string $status): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE agency_quotes
             SET status = :status,
                 sent_at = CASE WHEN :is_sent = 1 THEN :sent_now ELSE sent_at END,
                 decided_at = CASE WHEN :is_decided = 1 THEN :decided_now ELSE decided_at END,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'is_sent' => $status === 'sent' ? 1 : 0,
            'sent_now' => $now,
            'is_decided' => in_array($status, ['accepted', 'refused'], true) ? 1 : 0,
            'decided_now' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array{count: int, amount: int}
     */
    public function openPipeline(): array
    {
        $statement = $this->pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM agency_quotes WHERE status = 'sent'");
        $row = $statement === false ? false : $statement->fetch();

        return ['count' => (int) (is_array($row) ? $row['n'] : 0), 'amount' => (int) (is_array($row) ? $row['total'] : 0)];
    }
}
