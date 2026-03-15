<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JwtSessionSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JwtSessionSetting>
 */
class JwtSessionSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JwtSessionSetting::class);
    }

    /** @return JwtSessionSetting[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.updatedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(): ?JwtSessionSetting
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.updatedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(JwtSessionSetting $setting, bool $flush = false): void
    {
        $this->getEntityManager()->persist($setting);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(JwtSessionSetting $setting, bool $flush = false): void
    {
        $this->getEntityManager()->remove($setting);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
