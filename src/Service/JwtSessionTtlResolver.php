<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\JwtSessionSettingRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final class JwtSessionTtlResolver
{
    private const MIN_TTL_SECONDS = 300;
    private const MAX_TTL_SECONDS = 31536000;

    public function __construct(
        private readonly JwtSessionSettingRepository $settingRepository,
        #[Autowire('%env(int:JWT_TOKEN_TTL)%')] private readonly int $fallbackTtlSeconds,
    ) {
    }

    public function resolve(): int
    {
        try {
            $fromSetting = $this->settingRepository->findLatest()?->getTtlSeconds();
            if (is_int($fromSetting)) {
                return $this->normalize($fromSetting);
            }
        } catch (Throwable) {
            // Fallback to env-based TTL until migrations are applied.
        }

        return $this->normalize($this->fallbackTtlSeconds);
    }

    private function normalize(int $ttlSeconds): int
    {
        if ($ttlSeconds < self::MIN_TTL_SECONDS) {
            return self::MIN_TTL_SECONDS;
        }

        if ($ttlSeconds > self::MAX_TTL_SECONDS) {
            return self::MAX_TTL_SECONDS;
        }

        return $ttlSeconds;
    }
}
