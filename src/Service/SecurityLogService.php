<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SecurityLogRepository;

final class SecurityLogService
{
    public function __construct(private readonly SecurityLogRepository $repository)
    {
    }

    /**
     * @return array{service: string, total: int, page: int, perPage: int, pages: int, items: list<array<string, mixed>>}
     */
    public function getPaginatedList(int $page, int $perPage, string $service): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;
        $total   = $this->repository->countAll();
        $rows    = $this->repository->findPaginated($perPage, $offset);

        return [
            'service' => $service,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int) ceil($total / $perPage),
            'items'   => array_map(static fn(array $r) => [
                'id'          => (int) $r['id'],
                'ip'          => $r['ip'],
                'path'        => $r['path'],
                'method'      => $r['method'],
                'instanceId'  => $r['instance_id'],
                'isSensitive' => (bool) $r['is_sensitive'],
                'userAgent'   => $r['user_agent'],
                'createdAt'   => $r['created_at'],
            ], $rows),
        ];
    }

    public function clear(): int
    {
        return $this->repository->clearAll();
    }
}
