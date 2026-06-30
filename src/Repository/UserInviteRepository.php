<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserInvite;
use App\Traits\SaveRemoveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserInvite>
 */
class UserInviteRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInvite::class);
    }

    public function findByTokenHash(string $tokenHash): ?UserInvite
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function findPendingByUserId(string $userId): ?UserInvite
    {
        return $this->findOneBy([
            'userId' => $userId,
            'status' => UserInvite::STATUS_PENDING,
        ]);
    }
}
