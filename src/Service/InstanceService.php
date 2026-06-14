<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\InstanceRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class InstanceService
{
    public function __construct(
        private readonly InstanceRepository $instanceRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return array{id: string, name: string, subdomain: string}
     *
     * @throws BadRequestHttpException when subdomain is empty
     * @throws NotFoundHttpException when no instance matches
     */
    public function resolve(string $subdomain): array
    {
        if ($subdomain === '') {
            throw new BadRequestHttpException('Query parameter "subdomain" is required.');
        }

        $instance = $this->instanceRepository->findBySubdomain($subdomain);

        if ($instance === null) {
            throw new NotFoundHttpException('Instance not found.');
        }

        return [
            'id'        => (string) $instance->getId(),
            'name'      => $instance->getName(),
            'subdomain' => $instance->getSubdomain(),
        ];
    }

    /**
     * @return list<array{id: string, name: string, subdomain: string}>
     *
     * @throws UnauthorizedHttpException when the email cannot be resolved to a user
     */
    public function getInstancesForEmail(string $email): array
    {
        $userId = $this->userRepository->findIdByEmail($email);

        if ($userId === null) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        return $this->instanceRepository->findByUserId($userId);
    }
}
