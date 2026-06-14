<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Instance;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Instance>
 */
class InstanceRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly Connection $connection,
    ) {
        parent::__construct($registry, Instance::class);
    }

    public function findBySubdomain(string $subdomain): ?Instance
    {
        return $this->findOneBy(['subdomain' => $subdomain]);
    }

    /**
     * Return instances (id, name, subdomain) accessible by the given user ID.
     *
     * @return list<array{id: string, name: string, subdomain: string}>
     */
    public function findByUserId(string $userId): array
    {
        /** @var list<array{id: string, name: string, subdomain: string}> */
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT i.id, i.name, i.subdomain
                FROM instance i
                JOIN user_instance ui ON ui.instance_id = i.id
                WHERE ui.user_id = :userId
                ORDER BY i.name
            SQL,
            ['userId' => $userId],
        );
    }
}
