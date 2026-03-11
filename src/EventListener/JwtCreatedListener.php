<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Security\PermissionService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JwtCreatedListener
{
    public function __construct(private readonly PermissionService $permissionService)
    {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['firstName'] = $user->getFirstName();
        $payload['lastName'] = $user->getLastName();
        $payload['permissions'] = $this->permissionService->getPermissionsForUser($user);

        $event->setData($payload);
    }
}
