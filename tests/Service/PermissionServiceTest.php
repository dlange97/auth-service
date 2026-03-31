<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RoleDefinition;
use App\Entity\User;
use App\Repository\RoleDefinitionRepository;
use App\Security\PermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PermissionServiceTest extends TestCase
{
    private RoleDefinitionRepository&MockObject $roleRepo;
    private PermissionService $service;

    protected function setUp(): void
    {
        $this->roleRepo = $this->createMock(RoleDefinitionRepository::class);
        $this->service = new PermissionService($this->roleRepo);
    }

    private function makeUser(string $role): User
    {
        return (new User())
            ->setId('user-1')
            ->setEmail('test@example.com')
            ->setRoles([$role]);
    }

    /**
     * System roles must return their hardcoded permissions without any DB lookup.
     * PermissionService uses getStoredRoles(), so only the explicitly assigned role is
     * resolved — no implicit ROLE_USER expansion.
     */
    public function testSystemRoleReturnsHardcodedPermissionsWithoutDbLookup(): void
    {
        $this->roleRepo->expects($this->never())->method('findBySlug');

        $user = $this->makeUser(PermissionService::ROLE_EDITOR);
        $permissions = $this->service->getPermissionsForUser($user);

        $this->assertContains('dashboard.view', $permissions);
        $this->assertContains('todos.view', $permissions);
        $this->assertContains('todos.manage', $permissions);
        // ROLE_EDITOR does not grant admin-only permissions
        $this->assertNotContains('users.view', $permissions);
        $this->assertNotContains('users.assign_roles', $permissions);
        $this->assertNotContains('settings.view', $permissions);
    }

    /**
     * When a user's role is reassigned to a higher-privilege role,
     * getPermissionsForUser() must immediately reflect the new role's permissions.
     */
    public function testPermissionsUpdateWhenUserRoleIsReassigned(): void
    {
        $user = $this->makeUser(PermissionService::ROLE_USER);

        $permissionsAsUser = $this->service->getPermissionsForUser($user);
        $this->assertNotContains('users.view', $permissionsAsUser);
        $this->assertNotContains('settings.view', $permissionsAsUser);

        // Simulate assigning the user a new role (e.g. via UserManagementController::assignRole)
        $user->setRoles([PermissionService::ROLE_MANAGER]);

        $permissionsAsManager = $this->service->getPermissionsForUser($user);
        $this->assertContains('users.view', $permissionsAsManager);
        $this->assertContains('settings.view', $permissionsAsManager);
    }

    /**
     * When a user is demoted from a high-privilege role, permissions are removed.
     */
    public function testPermissionsReduceWhenUserRoleIsDowngraded(): void
    {
        $user = $this->makeUser(PermissionService::ROLE_ADMIN);

        $permissionsAsAdmin = $this->service->getPermissionsForUser($user);
        $this->assertContains('users.assign_roles', $permissionsAsAdmin);

        // Demote to ROLE_EDITOR
        $user->setRoles([PermissionService::ROLE_EDITOR]);

        $permissionsAsEditor = $this->service->getPermissionsForUser($user);
        $this->assertNotContains('users.assign_roles', $permissionsAsEditor);
        $this->assertNotContains('users.view', $permissionsAsEditor);
    }

    /**
     * Custom role permissions are fetched from the RoleDefinitionRepository.
     * Note: User::getRoles() always appends ROLE_USER, so base ROLE_USER permissions
     * are merged in. We assert on the permissions unique to the custom role.
     */
    public function testCustomRolePermissionsAreReturnedFromRepository(): void
    {
        // users.view is only granted by privileged roles — not by ROLE_USER
        $customRole = (new RoleDefinition())
            ->setName('Custom Viewer')
            ->setSlug('ROLE_CUSTOM_VIEWER')
            ->setPermissions(['dashboard.view', 'users.view'])
            ->setIsSystem(false);

        $this->roleRepo
            ->expects($this->once())
            ->method('findBySlug')
            ->with('ROLE_CUSTOM_VIEWER')
            ->willReturn($customRole);

        $user = $this->makeUser('ROLE_CUSTOM_VIEWER');
        $permissions = $this->service->getPermissionsForUser($user);

        $this->assertContains('dashboard.view', $permissions);
        // users.view comes exclusively from the custom role definition
        $this->assertContains('users.view', $permissions);
        // settings.view is not in the custom role OR in ROLE_USER
        $this->assertNotContains('settings.view', $permissions);
    }

    /**
     * When an admin updates a custom role's permissions (via RoleManagementService::updateRole),
     * the next call to getPermissionsForUser() for a user with that role returns the new permissions.
     * This works because permissions are derived from the RoleDefinition at call time — no cache.
     *
     * Note: User::getRoles() always appends ROLE_USER, so we verify using a permission that
     * is not part of ROLE_USER (e.g. users.view), making the before/after distinction clear.
     */
    public function testCustomRolePermissionUpdateIsImmediatelyReflected(): void
    {
        $customRole = (new RoleDefinition())
            ->setName('Limited Analyst')
            ->setSlug('ROLE_LIMITED_ANALYST')
            ->setPermissions(['dashboard.view'])
            ->setIsSystem(false);

        $this->roleRepo
            ->expects($this->exactly(2))
            ->method('findBySlug')
            ->with('ROLE_LIMITED_ANALYST')
            ->willReturn($customRole);

        $user = $this->makeUser('ROLE_LIMITED_ANALYST');

        // Before update: users.view is absent (not in ROLE_USER, not in the custom role yet)
        $before = $this->service->getPermissionsForUser($user);
        $this->assertNotContains('users.view', $before);

        // Simulate RoleManagementService::updateRole() adding users.view to this custom role
        $customRole->setPermissions(['dashboard.view', 'users.view']);

        // After update: users.view is now present — no extra sync step needed
        $after = $this->service->getPermissionsForUser($user);
        $this->assertContains('users.view', $after);
    }

    /**
     * A user assigned a role that has no matching RoleDefinition and is not a system role
     * receives no permissions at all — getStoredRoles() yields only that unknown slug, DB
     * lookup returns null, and no fallback is applied.  Privileged permissions must not leak.
     */
    public function testUnregisteredRoleAddsNoExtraPermissions(): void
    {
        $this->roleRepo
            ->expects($this->once())
            ->method('findBySlug')
            ->with('ROLE_UNKNOWN')
            ->willReturn(null);

        $user = $this->makeUser('ROLE_UNKNOWN');
        $permissions = $this->service->getPermissionsForUser($user);

        // The unregistered role definition is null → no additional grants beyond ROLE_USER
        $this->assertNotContains('users.view', $permissions);
        $this->assertNotContains('settings.view', $permissions);
        $this->assertNotContains('users.assign_roles', $permissions);
    }

    /**
     * userHasPermission() must return false before and true after a role is upgraded.
     */
    public function testUserHasPermissionReflectsRoleChange(): void
    {
        $user = $this->makeUser(PermissionService::ROLE_USER);

        $this->assertFalse(
            $this->service->userHasPermission($user, 'users.view'),
            'ROLE_USER must not have users.view',
        );

        // Promote to admin
        $user->setRoles([PermissionService::ROLE_ADMIN]);

        $this->assertTrue(
            $this->service->userHasPermission($user, 'users.view'),
            'ROLE_ADMIN must have users.view',
        );
        $this->assertTrue(
            $this->service->userHasPermission($user, 'users.assign_roles'),
            'ROLE_ADMIN must have users.assign_roles',
        );
    }

    /**
     * After custom role permissions are updated, userHasPermission() reflects
     * the new permission set for users assigned that role.
     * Uses users.view as the probe permission — not present in ROLE_USER, only
     * present when explicitly added to the custom role definition.
     */
    public function testUserHasPermissionReflectsCustomRolePermissionUpdate(): void
    {
        $customRole = (new RoleDefinition())
            ->setName('Custom Analyst')
            ->setSlug('ROLE_CUSTOM_ANALYST')
            ->setPermissions(['dashboard.view'])
            ->setIsSystem(false);

        $this->roleRepo
            ->method('findBySlug')
            ->with('ROLE_CUSTOM_ANALYST')
            ->willReturn($customRole);

        $user = $this->makeUser('ROLE_CUSTOM_ANALYST');

        // users.view not in ROLE_USER nor in the current custom role
        $this->assertFalse($this->service->userHasPermission($user, 'users.view'));

        // Simulate permission expansion from RoleManagementService::updateRole()
        $customRole->setPermissions(['dashboard.view', 'users.view']);

        $this->assertTrue($this->service->userHasPermission($user, 'users.view'));
    }
}
