<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Instance;
use App\Repository\InstanceRepository;
use App\Repository\UserRepository;
use App\Service\InstanceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class InstanceServiceTest extends TestCase
{
    private InstanceRepository&MockObject $instanceRepository;
    private UserRepository&MockObject $userRepository;
    private InstanceService $service;

    protected function setUp(): void
    {
        $this->instanceRepository = $this->createMock(InstanceRepository::class);
        $this->userRepository     = $this->createMock(UserRepository::class);

        $this->service = new InstanceService(
            $this->instanceRepository,
            $this->userRepository,
        );
    }

    public function testResolveThrowsOnEmptySubdomain(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Query parameter "subdomain" is required.');

        $this->service->resolve('');
    }

    public function testResolveThrowsWhenInstanceNotFound(): void
    {
        $this->instanceRepository->method('findBySubdomain')->with('unknown')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Instance not found.');

        $this->service->resolve('unknown');
    }

    public function testResolveReturnsSerializedInstance(): void
    {
        $instance = $this->buildInstance('uuid-1', 'Acme Corp', 'acme');
        $this->instanceRepository->method('findBySubdomain')->with('acme')->willReturn($instance);

        $result = $this->service->resolve('acme');

        $this->assertSame('uuid-1', $result['id']);
        $this->assertSame('Acme Corp', $result['name']);
        $this->assertSame('acme', $result['subdomain']);
    }

    public function testGetInstancesForEmailThrowsWhenUserNotFound(): void
    {
        $this->userRepository->method('findIdByEmail')->with('unknown@test.com')->willReturn(null);

        $this->expectException(UnauthorizedHttpException::class);

        $this->service->getInstancesForEmail('unknown@test.com');
    }

    public function testGetInstancesForEmailReturnsRows(): void
    {
        $this->userRepository->method('findIdByEmail')->with('user@test.com')->willReturn('user-uuid');
        $this->instanceRepository->method('findByUserId')->with('user-uuid')->willReturn([
            ['id' => 'i1', 'name' => 'Alpha', 'subdomain' => 'alpha'],
            ['id' => 'i2', 'name' => 'Beta', 'subdomain' => 'beta'],
        ]);

        $rows = $this->service->getInstancesForEmail('user@test.com');

        $this->assertCount(2, $rows);
        $this->assertSame('Alpha', $rows[0]['name']);
    }

    private function buildInstance(string $id, string $name, string $subdomain): Instance
    {
        $instance = new Instance();
        $instance->setId($id);
        $instance->setName($name);
        $instance->setSubdomain($subdomain);

        return $instance;
    }
}
