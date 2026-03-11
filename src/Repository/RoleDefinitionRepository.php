<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RoleDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoleDefinition>
 */
class RoleDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleDefinition::class);
    }

    /** @return RoleDefinition[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.isSystem', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?RoleDefinition
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function save(RoleDefinition $role, bool $flush = false): void
    {
        $this->getEntityManager()->persist($role);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RoleDefinition $role, bool $flush = false): void
    {
        $this->getEntityManager()->remove($role);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
