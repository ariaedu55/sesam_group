<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class QuoteRepository extends Repository
{
    private const COLUMNS = ['full_name', 'business_name', 'email', 'phone', 'activity', 'site_type', 'site_state', 'pages', 'features', 'has_domain', 'budget', 'deadline', 'inspiration', 'message'];

    /**
     * @param array<string, string|null> $data
     */
    public function insert(array $data): void
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            $values[$column] = $data[$column] ?? null;
        }
        $values['created_at'] = gmdate('Y-m-d H:i:s');
        $columns = array_keys($values);
        $statement = $this->pdo->prepare(
            'INSERT INTO contact_requests (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')'
        );
        $statement->execute($values);
    }
}
