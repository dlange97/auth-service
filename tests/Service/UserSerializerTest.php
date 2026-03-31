<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Security\PermissionService;
use App\Service\UserSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserSerializerTest extends TestCase
{
    private PermissionService&MockObject $permissionService;
    private UserSerializer $serializer;

    protected function setUp(): void
    {
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->serializer = new UserSerializer($this->permissionService);
    }

    public function testSerializeReturnsExpectedKeys(): void
    {
        $user = $this->makeUser();
        $this->permissionService->method('getPermissionsForUser')->willReturn(['users.list']);

        $result = $this->serializer->serialize($user);

        $expectedKeys = [
            'id', 'email', 'firstName', 'lastName', 'roles',
            'status', 'language', 'dashboardLayout', 'permissions', 'createdAt',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    public function testSerializeReturnsCorrectValues(): void
    {
        $user = $this->makeUser();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setLanguage('pl');

        $this->permissionService->method('getPermissionsForUser')->willReturn(['users.list', 'users.create']);

        $result = $this->serializer->serialize($user);

        $this->assertSame('user-1', $result['id']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('John', $result['firstName']);
        $this->assertSame('Doe', $result['lastName']);
        $this->assertContains('ROLE_USER', $result['roles']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('pl', $result['language']);
        $this->assertSame(['users.list', 'users.create'], $result['permissions']);
    }

    public function testSerializeReturnsNullForMissingOptionals(): void
    {
        $user = $this->makeUser();
        $this->permissionService->method('getPermissionsForUser')->willReturn([]);

        $result = $this->serializer->serialize($user);

        $this->assertNull($result['firstName']);
        $this->assertNull($result['lastName']);
        $this->assertNull($result['dashboardLayout']);
    }

    public function testSerializeFormatsCreatedAt(): void
    {
        $user = $this->makeUser();
        $this->permissionService->method('getPermissionsForUser')->willReturn([]);

        $result = $this->serializer->serialize($user);

        // createdAt is null for a freshly created entity not persisted
        $this->assertNull($result['createdAt']);
    }

    private function makeUser(): User
    {
        return (new User())
            ->setId('user-1')
            ->setEmail('test@example.com')
            ->setRoles(['ROLE_USER']);
    }
}
