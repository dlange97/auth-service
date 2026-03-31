<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\InstanceRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'auth_')]
class InstanceController extends AbstractController
{
    public function __construct(
        private readonly InstanceRepository $instanceRepository,
        private readonly Connection $connection,
    ) {
    }

    /** Resolve an instance by its subdomain slug (public, no auth required). */
    #[Route('/instances/resolve', name: 'instance_resolve', methods: ['GET'])]
    public function resolve(Request $request): JsonResponse
    {
        $subdomain = trim((string) $request->query->get('subdomain', ''));

        if ($subdomain === '') {
            return $this->json(['error' => 'Query parameter "subdomain" is required.'], Response::HTTP_BAD_REQUEST);
        }

        $instance = $this->instanceRepository->findBySubdomain($subdomain);

        if ($instance === null) {
            return $this->json(['error' => 'Instance not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $instance->getId(),
            'name' => $instance->getName(),
            'subdomain' => $instance->getSubdomain(),
        ]);
    }

    /** List all instances the authenticated user belongs to (requires JWT). */
    #[Route('/my-instances', name: 'my_instances', methods: ['GET'])]
    public function myInstances(): JsonResponse
    {
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT i.id, i.name, i.subdomain
                FROM instance i
                JOIN user_instance ui ON ui.instance_id = i.id
                WHERE ui.user_id = :userId
                ORDER BY i.name
            SQL,
            ['userId' => $this->resolveUserId($user->getUserIdentifier())],
        );

        return $this->json($rows);
    }

    private function resolveUserId(string $email): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT id FROM `user` WHERE email = :email',
            ['email' => $email],
        );
    }
}
