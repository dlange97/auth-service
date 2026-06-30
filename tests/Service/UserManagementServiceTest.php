<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\Locale\LanguagePolicy;
use App\Service\UserManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserManagementServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private PermissionService&MockObject $permissionService;
    private ValidatorInterface&MockObject $validator;
    private EntityManagerInterface&MockObject $em;
    private UserManagementService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new UserManagementService(
            $this->userRepository,
            $this->permissionService,
            $this->validator,
            $this->em,
            new LanguagePolicy(),
        );
    }

    public function testFindOrFailReturnsUser(): void
    {
        $user = $this->makeUser('user-1');
        $this->userRepository->method('findById')->willReturn($user);

        $result = $this->service->findOrFail('user-1');
        $this->assertSame($user, $result);
    }

    public function testFindOrFailThrowsNotFound(): void
    {
        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->service->findOrFail('nonexistent');
    }

    public function testUpdateUserUpdatesEmail(): void
    {
        $user = $this->makeUser('user-1');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->em->expects($this->once())->method('flush');

        $this->service->updateUser($user, ['email' => 'new@example.com']);

        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testUpdateUserThrowsOnEmptyEmail(): void
    {
        $user = $this->makeUser('user-1');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Email cannot be empty.');

        $this->service->updateUser($user, ['email' => '']);
    }

    public function testUpdateUserThrowsOnDuplicateEmail(): void
    {
        $user = $this->makeUser('user-1');
        $existing = $this->makeUser('user-2');
        $existing->setEmail('taken@example.com');

        $this->userRepository->method('findByEmail')->willReturn($existing);

        $this->expectException(ConflictHttpException::class);

        $this->service->updateUser($user, ['email' => 'taken@example.com']);
    }

    public function testUpdateUserUpdatesRole(): void
    {
        $user = $this->makeUser('user-1');
        $this->permissionService->method('isRoleSupported')->willReturn(true);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->service->updateUser($user, ['role' => 'ROLE_EDITOR']);

        $this->assertContains('ROLE_EDITOR', $user->getRoles());
    }

    public function testUpdateUserThrowsOnUnsupportedRole(): void
    {
        $user = $this->makeUser('user-1');
        $this->permissionService->method('isRoleSupported')->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported role.');

        $this->service->updateUser($user, ['role' => 'ROLE_BAD']);
    }

    public function testUpdateUserThrowsOnUnsupportedLanguage(): void
    {
        $user = $this->makeUser('user-1');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported language');

        $this->service->updateUser($user, ['language' => 'de']);
    }

    public function testAssignRoleSetsNewRole(): void
    {
        $user = $this->makeUser('user-1');
        $this->permissionService->method('isRoleSupported')->willReturn(true);
        $this->em->expects($this->once())->method('flush');

        $this->service->assignRole($user, 'ROLE_EDITOR');

        $this->assertContains('ROLE_EDITOR', $user->getRoles());
    }

    public function testAssignRoleThrowsOnUnsupported(): void
    {
        $user = $this->makeUser('user-1');
        $this->permissionService->method('isRoleSupported')->willReturn(false);

        $this->expectException(BadRequestHttpException::class);

        $this->service->assignRole($user, 'ROLE_BAD');
    }

    public function testSoftDeleteDeactivatesUser(): void
    {
        $user = $this->makeUser('user-1');
        $admin = $this->makeUser('admin-1');
        $this->em->expects($this->once())->method('flush');

        $this->service->softDelete($user, $admin);

        $this->assertSame(User::STATUS_INACTIVE, $user->getStatus());
    }

    public function testSoftDeleteThrowsWhenDeletingSelf(): void
    {
        $user = $this->makeUser('user-1');

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('You cannot deactivate your own account.');

        $this->service->softDelete($user, $user);
    }

    private function makeUser(string $id): User
    {
        return (new User())
            ->setId($id)
            ->setEmail($id . '@example.com')
            ->setRoles(['ROLE_USER']);
    }
}
