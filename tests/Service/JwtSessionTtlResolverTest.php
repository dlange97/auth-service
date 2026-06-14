<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JwtSessionSetting;
use App\Repository\JwtSessionSettingRepository;
use App\Service\JwtSessionTtlResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class JwtSessionTtlResolverTest extends TestCase
{
    private JwtSessionSettingRepository&MockObject $repository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(JwtSessionSettingRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testResolveReturnsNormalizedPersistedTtl(): void
    {
        $setting = new JwtSessionSetting();
        $setting->setName('Main');
        $setting->setTtlSeconds(120);

        $this->repository->expects($this->once())
            ->method('findLatest')
            ->willReturn($setting);

        $this->logger->expects($this->never())->method('warning');

        $resolver = new JwtSessionTtlResolver($this->repository, $this->logger, 3600);

        $this->assertSame(300, $resolver->resolve());
    }

    public function testResolveFallsBackAndLogsWhenRepositoryThrows(): void
    {
        $exception = new \RuntimeException('db unavailable');

        $this->repository->expects($this->once())
            ->method('findLatest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to resolve JWT session TTL from persisted settings. Falling back to configured TTL.',
                ['exception' => $exception],
            );

        $resolver = new JwtSessionTtlResolver($this->repository, $this->logger, 120);

        $this->assertSame(300, $resolver->resolve());
    }

    public function testResolveFallsBackWithoutLoggingWhenSettingMissing(): void
    {
        $this->repository->expects($this->once())
            ->method('findLatest')
            ->willReturn(null);

        $this->logger->expects($this->never())->method('warning');

        $resolver = new JwtSessionTtlResolver($this->repository, $this->logger, 999999999);

        $this->assertSame(31536000, $resolver->resolve());
    }
}
