<?php

declare(strict_types=1);

namespace App\Traits;

use MyDashboard\Shared\Traits\SaveRemoveTrait as SharedSaveRemoveTrait;

/**
 * Provides generic save / remove helpers for Doctrine repositories.
 * Requires the repository to extend ServiceEntityRepository (provides getEntityManager()).
 */
trait SaveRemoveTrait
{
    use SharedSaveRemoveTrait;
}
