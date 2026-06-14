<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InstanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'auth_')]
class InstanceController extends AbstractController
{
    public function __construct(private readonly InstanceService $instanceService)
    {
    }

    #[Route('/instances/resolve', name: 'instance_resolve', methods: ['GET'])]
    public function resolve(Request $request): JsonResponse
    {
        $subdomain = trim((string) $request->query->get('subdomain', ''));

        try {
            return $this->json($this->instanceService->resolve($subdomain));
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/my-instances', name: 'my_instances', methods: ['GET'])]
    public function myInstances(): JsonResponse
    {
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            return $this->json($this->instanceService->getInstancesForEmail($user->getUserIdentifier()));
        } catch (UnauthorizedHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }
}
