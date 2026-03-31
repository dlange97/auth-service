<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Instance;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Instance>
 */
class InstanceRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Instance::class);
    }

    public function findBySubdomain(string $subdomain): ?Instance
    {
        return $this->findOneBy(['subdomain' => $subdomain]);
    }
}
