<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckoutInvite;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CheckoutInvite>
 */
class CheckoutInviteRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckoutInvite::class);
    }

    public function findUnusedByHash(string $hash): ?CheckoutInvite
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.hash = :hash')
            ->andWhere('c.usedAt IS NULL')
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
