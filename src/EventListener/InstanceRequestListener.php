<?php

declare(strict_types=1);

namespace App\EventListener;

use MyDashboard\Shared\EventListener\InstanceRequestListener as SharedInstanceRequestListener;

/**
 * auth-service instance listener.
 *
 * Bypass paths:
 *   /auth/health          – health check (no tenant)
 *   /auth/login           – public login endpoint
 *   /auth/request-access  – public access request form
 *   /auth/checkout        – public checkout form (creates new instance)
 *   /auth/docs            – API docs (dev)
 */
final readonly class InstanceRequestListener extends SharedInstanceRequestListener
{
    public function __construct()
    {
        parent::__construct([
            '/auth/health',
            '/auth/login',
            '/auth/request-access',
            '/auth/checkout',
            '/auth/docs',
        ]);
    }
}
