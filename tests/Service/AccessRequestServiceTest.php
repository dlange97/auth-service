<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\AccessRequestService;
use App\Service\NotificationGateway;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AccessRequestServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private PermissionService&MockObject $permissionService;
    private NotificationGateway&MockObject $notificationGateway;
    private AccessRequestService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->notificationGateway = $this->createMock(NotificationGateway::class);

        $this->service = new AccessRequestService(
            $this->userRepository,
            $this->permissionService,
            $this->notificationGateway,
        );
    }

    public function testSendRequestThrowsOnEmptyEmail(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Email is required.');

        $this->service->sendRequest(['email' => '']);
    }

    public function testSendRequestThrowsOnMissingEmail(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->service->sendRequest([]);
    }

    public function testSendRequestCallsGatewayWithRecipients(): void
    {
        $admin = $this->makeUser('admin-1', 'admin@example.com');
        $regular = $this->makeUser('user-1', 'user@example.com');

        $this->userRepository->method('findAllOrdered')->willReturn([$admin, $regular]);

        $this->permissionService->method('userHasPermission')
            ->willReturnCallback(function (User $user, string $permission) {
                return $user->getId() === 'admin-1' && $permission === 'users.create';
            });

        $this->notificationGateway->expects($this->once())
            ->method('sendRequestAccess')
            ->with(
                $this->callback(fn(array $data) => $data['email'] === 'requester@example.com'),
                $this->callback(fn(array $recipients) => count($recipients) === 1 && $recipients[0]['id'] === 'admin-1'),
            )
            ->willReturn(true);

        $this->service->sendRequest([
            'email'     => 'requester@example.com',
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'message'   => 'Please grant access',
        ]);
    }

    public function testSendRequestThrows502WhenGatewayFails(): void
    {
        $this->userRepository->method('findAllOrdered')->willReturn([]);

        $this->notificationGateway->method('sendRequestAccess')->willReturn(false);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unable to send access request');

        $this->service->sendRequest(['email' => 'test@example.com']);
    }

    public function testSendRequestTrimsInputValues(): void
    {
        $this->userRepository->method('findAllOrdered')->willReturn([]);

            $payloadMatcher = $this->callback(function (array $data): bool {
                return $data['email'] === 'test@example.com'
                    && $data['firstName'] === 'John'
                    && $data['lastName'] === 'Doe'
                    && $data['message'] === 'Hello';
            });

        $this->notificationGateway->expects($this->once())
            ->method('sendRequestAccess')
                ->with($payloadMatcher, $this->anything())
            ->willReturn(true);

        $this->service->sendRequest([
            'email'     => '  test@example.com  ',
            'firstName' => '  John  ',
            'lastName'  => '  Doe  ',
            'message'   => '  Hello  ',
        ]);
    }

    private function makeUser(string $id, string $email): User
    {
        return (new User())
            ->setId($id)
            ->setEmail($email)
            ->setRoles(['ROLE_USER']);
    }
}
