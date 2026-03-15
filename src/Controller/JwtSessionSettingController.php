<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\JwtSessionSettingService;
use App\Service\PermissionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/settings/jwt-session', name: 'auth_jwt_session_')]
class JwtSessionSettingController extends AbstractController
{
    public function __construct(
        private readonly JwtSessionSettingService $settingService,
        private readonly PermissionGuard $permissionGuard,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensureAdmin($user);

        return $this->json($this->settingService->list());
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensureAdmin($user);

        return $this->json($this->settingService->findOrFail($id));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensureAdmin($user);

        $data   = json_decode($request->getContent(), true);
        $result = $this->settingService->createOrReplace(is_array($data) ? $data : []);
        $status = $result['isNew'] ? Response::HTTP_CREATED : Response::HTTP_OK;

        return $this->json($result['data'], $status);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensureAdmin($user);

        $data = json_decode($request->getContent(), true);

        return $this->json($this->settingService->update($id, is_array($data) ? $data : []));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensureAdmin($user);

        $this->settingService->delete($id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
