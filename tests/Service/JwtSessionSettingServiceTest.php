<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JwtSessionSetting;
use App\Repository\JwtSessionSettingRepository;
use App\Service\JwtSessionSettingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class JwtSessionSettingServiceTest extends TestCase
{
    private JwtSessionSettingRepository&MockObject $repo;
    private ValidatorInterface&MockObject $validator;
    private JwtSessionSettingService $service;

    protected function setUp(): void
    {
        $this->repo      = $this->createMock(JwtSessionSettingRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->service   = new JwtSessionSettingService($this->repo, $this->validator);
    }

    public function testListReturnsSerializedItems(): void
    {
        $setting = $this->makeSetting(1, 'Main', 3600);

        $this->repo->expects($this->once())
            ->method('findAllOrdered')
            ->willReturn([$setting]);

        $result = $this->service->list();

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Main', $result[0]['name']);
        $this->assertSame(3600, $result[0]['ttlSeconds']);
        $this->assertEqualsWithDelta(0.04, $result[0]['ttlDays'], 0.01);
    }

    public function testFindOrFailThrowsNotFoundForMissingId(): void
    {
        $this->repo->method('find')->with(99)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->findOrFail(99);
    }

    public function testDeleteThrowsConflictWhenLastSetting(): void
    {
        $setting = $this->makeSetting(1, 'Only', 7200);

        $this->repo->method('find')->with(1)->willReturn($setting);
        $this->repo->method('findAllOrdered')->willReturn([$setting]);

        $this->expectException(ConflictHttpException::class);

        $this->service->delete(1);
    }

    public function testDeleteThrowsNotFoundForMissingId(): void
    {
        $this->repo->method('find')->with(5)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->delete(5);
    }

    public function testCreateOrReplaceThrowsBadRequestWhenTtlMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->service->createOrReplace(['name' => 'No TTL']);
    }

    public function testCreateOrReplaceCreatesNewSettingWithNormalizedName(): void
    {
        $this->repo->method('findLatest')->willReturn(null);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->method('findAllOrdered')->willReturn([]);
        $this->repo->expects($this->once())->method('save');

        $result = $this->service->createOrReplace(['ttlSeconds' => 86400]);

        $this->assertTrue($result['isNew']);
        $this->assertSame('Default JWT Session', $result['data']['name']);
        $this->assertSame(86400, $result['data']['ttlSeconds']);
        $this->assertSame(1.0, $result['data']['ttlDays']);
    }

    public function testCreateOrReplaceUsesProvidedName(): void
    {
        $this->repo->method('findLatest')->willReturn(null);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->method('findAllOrdered')->willReturn([]);

        $result = $this->service->createOrReplace(['name' => '  Custom  ', 'ttlDays' => 2]);

        $this->assertSame('Custom', $result['data']['name']);
        $this->assertSame(172800, $result['data']['ttlSeconds']);
    }

    public function testUpdateThrowsNotFoundForMissingId(): void
    {
        $this->repo->method('find')->with(7)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->update(7, ['ttlSeconds' => 3600]);
    }

    public function testUpdateAppliesChanges(): void
    {
        $setting = $this->makeSetting(3, 'Old', 3600);

        $this->repo->method('find')->with(3)->willReturn($setting);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->expects($this->once())->method('save');

        $result = $this->service->update(3, ['name' => 'New', 'ttlSeconds' => 7200]);

        $this->assertSame('New', $result['name']);
        $this->assertSame(7200, $result['ttlSeconds']);
    }

    private function makeSetting(int $id, string $name, int $ttlSeconds): JwtSessionSetting
    {
        $setting = new JwtSessionSetting();
        $setting->setName($name);
        $setting->setTtlSeconds($ttlSeconds);

        $ref = new \ReflectionProperty(JwtSessionSetting::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($setting, $id);

        return $setting;
    }
}
