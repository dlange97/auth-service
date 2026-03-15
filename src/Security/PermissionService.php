<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\RoleDefinitionRepository;

class PermissionService
{
    public function __construct(
        private readonly ?RoleDefinitionRepository $roleRepo = null,
    ) {
    }


    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_MANAGER = 'ROLE_MANAGER';
    public const ROLE_EDITOR = 'ROLE_EDITOR';
    public const ROLE_USER = 'ROLE_USER';

    private const ROLE_PERMISSIONS = [
        self::ROLE_ADMIN => [
            'dashboard.view',
            'todos.view',
            'todos.manage',
            'shopping.view',
            'shopping.manage',
            'events.view',
            'events.manage',
            'map.view',
            'routes.manage',
            'users.view',
            'users.create',
            'users.assign_roles',
            'settings.view',
        ],
        self::ROLE_MANAGER => [
            'dashboard.view',
            'todos.view',
            'todos.manage',
            'shopping.view',
            'shopping.manage',
            'events.view',
            'events.manage',
            'map.view',
            'routes.manage',
            'users.view',
            'users.create',
            'settings.view',
        ],
        self::ROLE_EDITOR => [
            'dashboard.view',
            'todos.view',
            'todos.manage',
            'shopping.view',
            'shopping.manage',
            'events.view',
            'events.manage',
            'map.view',
            'routes.manage',
        ],
        self::ROLE_USER => [
            'dashboard.view',
            'todos.view',
            'todos.manage',
            'shopping.view',
            'shopping.manage',
            'events.view',
            'events.manage',
            'map.view',
            'routes.manage',
        ],
    ];

    /** @return list<string> */
    public function getSupportedRoles(): array
    {
        $static = array_keys(self::ROLE_PERMISSIONS);
        if ($this->roleRepo === null) {
            return $static;
        }
        $custom = array_map(
            fn($r) => $r->getSlug(),
            array_filter($this->roleRepo->findAllOrdered(), fn($r) => !$r->isSystem()),
        );

        return array_values(array_unique([...$static, ...$custom]));
    }

    public function isRoleSupported(string $role): bool
    {
        if (isset(self::ROLE_PERMISSIONS[$role])) {
            return true;
        }
        if ($this->roleRepo !== null) {
            return $this->roleRepo->findBySlug($role) !== null;
        }
        return false;
    }

    /** @return list<string> */
    public function getAllPermissions(): array
    {
        $all = [];
        foreach (self::ROLE_PERMISSIONS as $permissions) {
            $all = [...$all, ...$permissions];
        }

        return array_values(array_unique($all));
    }

    /** @return array<string, list<string>> */
    public function getRolePermissionsMap(): array
    {
        $map = self::ROLE_PERMISSIONS;

        if ($this->roleRepo !== null) {
            foreach ($this->roleRepo->findAllOrdered() as $role) {
                if (!isset($map[$role->getSlug()])) {
                    $map[$role->getSlug()] = $role->getPermissions();
                }
            }
        }

        return $map;
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public function getPermissionsForRoles(array $roles): array
    {
        $permissions = [];
        foreach ($roles as $role) {
            if (isset(self::ROLE_PERMISSIONS[$role])) {
                $permissions = [...$permissions, ...self::ROLE_PERMISSIONS[$role]];
                continue;
            }
            // Check dynamic custom roles from DB
            if ($this->roleRepo !== null) {
                $def = $this->roleRepo->findBySlug($role);
                if ($def !== null) {
                    $permissions = [...$permissions, ...$def->getPermissions()];
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /** @return list<string> */
    public function getPermissionsForUser(User $user): array
    {
        return $this->getPermissionsForRoles($user->getRoles());
    }

    public function userHasPermission(User $user, string $permission): bool
    {
        return in_array($permission, $this->getPermissionsForUser($user), true);
    }
}
