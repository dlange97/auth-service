<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Provides generic save / remove helpers for Doctrine repositories.
 * Requires the repository to extend ServiceEntityRepository (provides getEntityManager()).
 */
trait SaveRemoveTrait
{
    public function save(object $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(object $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
