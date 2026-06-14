<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\SecurityLogRepository;
use App\Service\SecurityLogService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SecurityLogServiceTest extends TestCase
{
    private SecurityLogRepository&MockObject $repository;
    private SecurityLogService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SecurityLogRepository::class);
        $this->service    = new SecurityLogService($this->repository);
    }

    public function testGetPaginatedListReturnsMappedItems(): void
    {
        $this->repository->method('countAll')->willReturn(3);
        $this->repository->method('findPaginated')->with(10, 0)->willReturn([
            ['id' => '1', 'ip' => '127.0.0.1', 'path' => '/api/login', 'method' => 'POST', 'instance_id' => 'inst-1', 'is_sensitive' => '1', 'user_agent' => 'PHPUnit', 'created_at' => '2026-01-01 10:00:00'],
        ]);

        $result = $this->service->getPaginatedList(1, 10, 'auth');

        $this->assertSame('auth', $result['service']);
        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['perPage']);
        $this->assertSame(1, $result['pages']);
        $this->assertCount(1, $result['items']);

        $item = $result['items'][0];
        $this->assertSame(1, $item['id']);
        $this->assertSame('127.0.0.1', $item['ip']);
        $this->assertTrue($item['isSensitive']);
    }

    public function testGetPaginatedListClampsPageAndPerPage(): void
    {
        $this->repository->method('countAll')->willReturn(0);
        $this->repository
            ->expects($this->once())
            ->method('findPaginated')
            ->with(100, 0) // perPage clamped to 100, page clamped to 1
            ->willReturn([]);

        $this->service->getPaginatedList(0, 9999, 'auth');
    }

    public function testGetPaginatedListCalculatesOffsetCorrectly(): void
    {
        $this->repository->method('countAll')->willReturn(50);
        $this->repository
            ->expects($this->once())
            ->method('findPaginated')
            ->with(10, 20) // page=3, perPage=10 → offset=20
            ->willReturn([]);

        $this->service->getPaginatedList(3, 10, 'auth');
    }

    public function testClearDelegatesAndReturnsCount(): void
    {
        $this->repository->expects($this->once())->method('clearAll')->willReturn(42);

        $this->assertSame(42, $this->service->clear());
    }
}
