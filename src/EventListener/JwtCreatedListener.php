<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Security\PermissionService;
use App\Service\JwtSessionTtlResolver;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JwtCreatedListener
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly JwtSessionTtlResolver $jwtSessionTtlResolver,
    ) {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $payload = $event->getData();
        $issuedAt = time();
        $ttlSeconds = $this->jwtSessionTtlResolver->resolve();

        $payload['id'] = $user->getId();
        $payload['firstName'] = $user->getFirstName();
        $payload['lastName'] = $user->getLastName();
        $payload['instanceId'] = $this->resolveInstanceId($user);
        $payload['permissions'] = $this->permissionService->getPermissionsForUser($user);
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + $ttlSeconds;
        $payload['sessionTtlSeconds'] = $ttlSeconds;

        $event->setData($payload);
    }

    private function resolveInstanceId(User $user): ?string
    {
        $instances = $user->getInstances();

        if ($instances->count() === 1) {
            return $instances->first()->getId();
        }

        if ($instances->count() > 1) {
            return null;
        }

        return $user->getInstanceId();
    }
}
