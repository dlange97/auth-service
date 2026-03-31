<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\PermissionService;

final class UserSerializer
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    /** @return array<string, mixed> */
    public function serialize(User $user): array
    {
        return [
            'id'              => $user->getId(),
            'email'           => $user->getEmail(),
            'firstName'       => $user->getFirstName(),
            'lastName'        => $user->getLastName(),
            'roles'           => $user->getRoles(),
            'status'          => $user->getStatus(),
            'language'        => $user->getLanguage(),
            'dashboardLayout' => $user->getDashboardLayout(),
            'permissions'     => $this->permissionService->getPermissionsForUser($user),
            'createdAt'       => $user->getCreatedAt()?->format('c'),
        ];
    }
}
