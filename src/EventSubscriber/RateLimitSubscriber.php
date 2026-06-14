<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use MyDashboard\Shared\EventSubscriber\RateLimitSubscriber as BaseRateLimitSubscriber;

final class RateLimitSubscriber extends BaseRateLimitSubscriber
{
    protected array $bypassPaths = [
        '/auth/health',
        '/auth/instances/resolve',
        '/auth/checkout',
    ];

    protected array $sensitivePaths = [
        '/auth/login',
        '/auth/register',
        '/auth/request-access',
    ];
}
