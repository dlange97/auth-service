<?php

declare(strict_types=1);

namespace App\Traits;

use MyDashboard\Shared\Traits\HasInstanceId as SharedHasInstanceId;

/**
 * Adds instanceId (VARCHAR 36) to any entity.
 * The owning class MUST carry #[ORM\HasLifecycleCallbacks].
 */
trait HasInstanceId
{
    use SharedHasInstanceId;
}
