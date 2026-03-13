<?php

declare(strict_types=1);

namespace App\Traits;

use MyDashboard\Shared\Traits\TimestampableTrait as SharedTimestampableTrait;

/**
 * Adds createdAt, updatedAt, createdBy, updatedBy to any entity.
 * The owning class MUST carry #[ORM\HasLifecycleCallbacks].
 */
trait TimestampableTrait
{
    use SharedTimestampableTrait;
}
