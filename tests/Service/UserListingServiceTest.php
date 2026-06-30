<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\UserListingService;
use App\Service\UserSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserListingServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserListingService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $permissionService = $this->createMock(PermissionService::class);
        $this->service = new UserListingService($this->userRepository, new UserSerializer($permissionService));
    }

    public function testListUsersReturnsSerializedItemsAndPagination(): void
    {
        $user = (new User())
            ->setId('user-1')
            ->setEmail('john@example.com')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setRoles(['ROLE_EDITOR'])
            ->setStatus(User::STATUS_ACTIVE);

        $this->userRepository
            ->expects($this->once())
            ->method('findPaginated')
            ->with('john', 2, 5)
            ->willReturn([
                'items' => [$user],
                'total' => 12,
                'page' => 2,
                'perPage' => 5,
                'totalPages' => 3,
            ]);

        $result = $this->service->listUsers('john', 2, 5);

        $this->assertSame(12, $result['pagination']['total']);
        $this->assertSame(2, $result['pagination']['page']);
        $this->assertSame(5, $result['pagination']['perPage']);
        $this->assertSame(3, $result['pagination']['totalPages']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('user-1', $result['items'][0]['id']);
        $this->assertSame('john@example.com', $result['items'][0]['email']);
        $this->assertSame('John', $result['items'][0]['firstName']);
        $this->assertSame('Doe', $result['items'][0]['lastName']);
        $this->assertSame(User::STATUS_ACTIVE, $result['items'][0]['status']);
    }
}
