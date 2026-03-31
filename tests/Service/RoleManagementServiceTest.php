<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RoleDefinition;
use App\Repository\RoleDefinitionRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\RoleManagementService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RoleManagementServiceTest extends TestCase
{
    private RoleDefinitionRepository&MockObject $roleRepository;
    private UserRepository&MockObject $userRepository;
    private PermissionService&MockObject $permissionService;
    private ValidatorInterface&MockObject $validator;
    private RoleManagementService $service;

    protected function setUp(): void
    {
        $this->roleRepository = $this->createMock(RoleDefinitionRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->service = new RoleManagementService(
            $this->roleRepository,
            $this->userRepository,
            $this->permissionService,
            $this->validator,
        );
    }

    public function testDeleteRoleThrowsConflictWhenAssignedToUsers(): void
    {
        $role = (new RoleDefinition())
            ->setName('Custom role')
            ->setSlug('ROLE_CUSTOM')
            ->setPermissions(['dashboard.view'])
            ->setIsSystem(false);

        $this->roleRepository
            ->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($role);

        $this->userRepository
            ->expects($this->once())
            ->method('countByRoleSlug')
            ->with('ROLE_CUSTOM')
            ->willReturn(2);

        $this->roleRepository->expects($this->never())->method('remove');

        $this->expectException(ConflictHttpException::class);

        $this->service->deleteRole(10);
    }

    public function testCreateRoleFiltersPermissionsAndReturnsAssignedCount(): void
    {
        $this->permissionService
            ->expects($this->once())
            ->method('getAllPermissions')
            ->willReturn(['dashboard.view', 'users.view']);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->roleRepository
            ->expects($this->once())
            ->method('slugExists')
            ->with('ROLE_CUSTOM_ANALYST', null)
            ->willReturn(false);

        $this->roleRepository->expects($this->once())->method('save');

        $this->userRepository
            ->expects($this->once())
            ->method('countByRoleSlug')
            ->with('ROLE_CUSTOM_ANALYST')
            ->willReturn(0);

        $result = $this->service->createRole([
            'name' => 'Custom Analyst',
            'slug' => 'role_custom_analyst',
            'permissions' => ['dashboard.view', 'not.allowed'],
        ]);

        $this->assertSame('Custom Analyst', $result['name']);
        $this->assertSame('ROLE_CUSTOM_ANALYST', $result['slug']);
        $this->assertSame(['dashboard.view'], $result['permissions']);
        $this->assertSame(0, $result['assignedUsersCount']);
    }

    /**
     * When a custom role's permissions are updated via updateRole(), the new permission list
     * is filtered against allowed permissions, persisted, and returned in the response.
     * Any users with this role will receive the updated permissions on their next permission check.
     */
    public function testUpdateCustomRolePermissionsArePersisted(): void
    {
        $role = (new RoleDefinition())
            ->setName('Custom Role')
            ->setSlug('ROLE_CUSTOM')
            ->setPermissions(['dashboard.view'])
            ->setIsSystem(false);

        $this->roleRepository
            ->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($role);

        $this->permissionService
            ->expects($this->once())
            ->method('getAllPermissions')
            ->willReturn(['dashboard.view', 'todos.view', 'shopping.view']);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->roleRepository->expects($this->once())->method('save');

        $this->userRepository
            ->expects($this->once())
            ->method('countByRoleSlug')
            ->with('ROLE_CUSTOM')
            ->willReturn(3);

        $result = $this->service->updateRole(5, [
            'permissions' => ['dashboard.view', 'todos.view', 'not.allowed.permission'],
        ]);

        // Only allowed permissions pass the filter
        $this->assertSame(['dashboard.view', 'todos.view'], $result['permissions']);
        // Affected user count is included so callers know how many users are impacted
        $this->assertSame(3, $result['assignedUsersCount']);
        $this->assertSame('ROLE_CUSTOM', $result['slug']);
    }

    /**
     * System role permissions are hardcoded and must not change via updateRole(),
     * even if a permissions array is passed in the request data.
     */
    public function testUpdateSystemRoleIgnoresPermissionChanges(): void
    {
        $role = (new RoleDefinition())
            ->setName('Administrator')
            ->setSlug('ROLE_ADMIN')
            ->setPermissions(['dashboard.view', 'users.view', 'users.assign_roles'])
            ->setIsSystem(true);

        $this->roleRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($role);

        // System role path must not call permission filtering
        $this->permissionService->expects($this->never())->method('getAllPermissions');
        // No name supplied → no save
        $this->roleRepository->expects($this->never())->method('save');

        $this->userRepository
            ->expects($this->once())
            ->method('countByRoleSlug')
            ->with('ROLE_ADMIN')
            ->willReturn(1);

        $result = $this->service->updateRole(1, [
            'permissions' => ['dashboard.view'], // attempt to narrow permissions — must be ignored
        ]);

        // Original permissions are preserved unchanged
        $this->assertSame(
            ['dashboard.view', 'users.view', 'users.assign_roles'],
            $result['permissions'],
        );
    }

    /**
     * System roles allow their display name to be updated; permissions remain unchanged.
     */
    public function testUpdateSystemRoleAllowsNameChange(): void
    {
        $role = (new RoleDefinition())
            ->setName('Admin')
            ->setSlug('ROLE_ADMIN')
            ->setPermissions(['dashboard.view', 'users.view'])
            ->setIsSystem(true);

        $this->roleRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($role);

        $this->permissionService->expects($this->never())->method('getAllPermissions');
        $this->roleRepository->expects($this->once())->method('save');

        $this->userRepository
            ->expects($this->once())
            ->method('countByRoleSlug')
            ->with('ROLE_ADMIN')
            ->willReturn(2);

        $result = $this->service->updateRole(1, ['name' => 'Super Administrator']);

        $this->assertSame('Super Administrator', $result['name']);
        // Permissions must not be affected by a name-only update
        $this->assertSame(['dashboard.view', 'users.view'], $result['permissions']);
        $this->assertSame(2, $result['assignedUsersCount']);
    }
}
