<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveByEmail(string $email): ?User
    {
        return $this->findOneBy([
            'email' => $email,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param string $id UUID string (char(36))
     */
    public function findById(string $id): ?User
    {
        return $this->find($id);
    }

    /** @return list<User> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items:list<User>,total:int,page:int,perPage:int,totalPages:int}
     */
    public function findPaginated(string $search, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('u');
        $search = trim($search);

        if ($search !== '') {
            $term = '%' . mb_strtolower($search) . '%';
            $qb
                ->andWhere("LOWER(u.email) LIKE :term OR LOWER(COALESCE(u.firstName, '')) LIKE :term OR LOWER(COALESCE(u.lastName, '')) LIKE :term")
                ->setParameter('term', $term);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $totalPages = max(1, (int) ceil($total / max($perPage, 1)));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    public function countByRoleSlug(string $slug): int
    {
        $sql = 'SELECT COUNT(*) FROM `user` u WHERE JSON_CONTAINS(u.roles, :roleJson) = 1';

        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne($sql, ['roleJson' => json_encode($slug)]);
    }

    /** @return list<User> */
    public function findByRoleSlug(string $slug): array
    {
        $ids = $this->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn(
                'SELECT id FROM `user` WHERE JSON_CONTAINS(roles, :roleJson) = 1',
                ['roleJson' => json_encode($slug)],
            );

        if ($ids === []) {
            return [];
        }

        /** @var list<User> */
        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
