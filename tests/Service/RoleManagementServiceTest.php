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
}
