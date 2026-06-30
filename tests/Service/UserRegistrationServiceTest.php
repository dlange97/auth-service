<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\UserRegistrationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserRegistrationServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ValidatorInterface&MockObject $validator;
    private PermissionService&MockObject $permissionService;
    private UserRegistrationService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->permissionService = $this->createMock(PermissionService::class);

        $this->service = new UserRegistrationService(
            $this->userRepository,
            $this->passwordHasher,
            $this->validator,
            $this->permissionService,
        );
    }

    public function testRegisterCreatesUserWithRoleUser(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->expects($this->once())->method('save');

        $user = $this->service->register([
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
        ]);

        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('Jan', $user->getFirstName());
        $this->assertSame('Kowalski', $user->getLastName());
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertNotNull($user->getId());
    }

    public function testRegisterThrowsOnEmptyEmail(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Email and password are required.');

        $this->service->register(['email' => '', 'password' => 'SecurePass123']);
    }

    public function testRegisterThrowsOnEmptyPassword(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Email and password are required.');

        $this->service->register(['email' => 'test@example.com', 'password' => '']);
    }

    public function testRegisterThrowsOnShortPassword(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters.');

        $this->service->register(['email' => 'test@example.com', 'password' => 'short']);
    }

    public function testRegisterThrowsOnDuplicateEmail(): void
    {
        $existing = (new User())->setId('existing-id')->setEmail('test@example.com');
        $this->userRepository->method('findByEmail')->willReturn($existing);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $this->service->register(['email' => 'test@example.com', 'password' => 'SecurePass123']);
    }

    public function testCreateUserAssignsSpecifiedRole(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->permissionService->method('isRoleSupported')->willReturn(true);
        $this->userRepository->expects($this->once())->method('save');

        $user = $this->service->createUser([
            'email' => 'admin@example.com',
            'password' => 'AdminPass123',
            'role' => 'ROLE_EDITOR',
        ]);

        $this->assertContains('ROLE_EDITOR', $user->getRoles());
    }

    public function testCreateUserThrowsOnUnsupportedRole(): void
    {
        $this->permissionService->method('isRoleSupported')->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported role.');

        $this->service->createUser([
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'role' => 'ROLE_NONEXISTENT',
        ]);
    }

    public function testCreateInvitedUserHasInvitedStatusAndNoPassword(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->permissionService->method('isRoleSupported')->willReturn(true);
        $this->passwordHasher->expects($this->never())->method('hashPassword');
        $this->userRepository->expects($this->once())->method('save');

        $user = $this->service->createInvitedUser([
            'email' => 'invitee@example.com',
            'role' => 'ROLE_EDITOR',
            'firstName' => 'Inv',
            'lastName' => 'Itee',
        ]);

        $this->assertSame(User::STATUS_INVITED, $user->getStatus());
        $this->assertFalse($user->isActive());
        $this->assertNull($user->getPassword());
        $this->assertContains('ROLE_EDITOR', $user->getRoles());
        $this->assertNotNull($user->getId());
    }

    public function testCreateInvitedUserThrowsOnEmptyEmail(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Email is required.');

        $this->service->createInvitedUser(['email' => '', 'role' => 'ROLE_USER']);
    }

    public function testCreateInvitedUserThrowsOnDuplicateEmail(): void
    {
        $this->permissionService->method('isRoleSupported')->willReturn(true);
        $existing = (new User())->setId('existing-id')->setEmail('invitee@example.com');
        $this->userRepository->method('findByEmail')->willReturn($existing);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $this->service->createInvitedUser(['email' => 'invitee@example.com', 'role' => 'ROLE_USER']);
    }
}
