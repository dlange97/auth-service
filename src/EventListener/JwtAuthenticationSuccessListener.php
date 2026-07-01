<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\JwtSessionTtlResolver;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;

final class JwtAuthenticationSuccessListener
{
    public function __construct(
        private readonly JwtSessionTtlResolver $jwtSessionTtlResolver,
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $token = $data['token'] ?? null;
        if (!is_string($token) || $token === '') {
            return;
        }

        $ttlSeconds = $this->jwtSessionTtlResolver->resolve();
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $ttlSeconds));

        $event->getResponse()->headers->setCookie(
            Cookie::create('jwt_token')
                ->withValue($token)
                ->withExpires($expiresAt)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }
}
