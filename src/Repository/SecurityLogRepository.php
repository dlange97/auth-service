<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

class SecurityLogRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function countAll(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM rate_limit_log');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /** @var list<array<string, mixed>> */
        return $this->connection->fetchAllAssociative(
            'SELECT id, ip, path, method, instance_id, is_sensitive, user_agent, created_at
             FROM rate_limit_log
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset],
        );
    }

    public function clearAll(): int
    {
        return (int) $this->connection->executeStatement('DELETE FROM rate_limit_log');
    }
}
