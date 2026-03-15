<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\PermissionService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PermissionGuard
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    public function ensure(User $user, string $permission): void
    {
        if ($this->permissionService->userHasPermission($user, $permission)) {
            return;
        }

        throw new AccessDeniedHttpException(sprintf('Forbidden. Missing permission: %s', $permission));
    }

    public function ensureAdmin(User $user): void
    {
        if (in_array(PermissionService::ROLE_ADMIN, $user->getRoles(), true)) {
            return;
        }

        throw new AccessDeniedHttpException('Forbidden. Admin role required.');
    }
}
