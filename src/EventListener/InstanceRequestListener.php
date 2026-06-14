<?php

declare(strict_types=1);

namespace App\EventListener;

use MyDashboard\Shared\EventListener\InstanceRequestListener as SharedInstanceRequestListener;

final readonly class InstanceRequestListener extends SharedInstanceRequestListener
{
    public function __construct()
    {
        parent::__construct([
            '/auth/health',
            '/auth/login',
            '/auth/request-access',
            '/auth/checkout',
            '/auth/register',
            '/auth/docs',
            '/auth/instances',
            '/auth/my-instances',
        ]);
    }
}
