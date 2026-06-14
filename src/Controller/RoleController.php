<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\PermissionGuard;
use App\Service\RoleManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/roles', name: 'auth_roles_')]
class RoleController extends AbstractController
{
    public function __construct(
        private readonly PermissionGuard $permissionGuard,
        private readonly RoleManagementService $roleManagementService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listRoles(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensure($user, 'settings.view');

        return $this->json($this->roleManagementService->listRoles());
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensure($user, 'settings.view');

        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleRoleAction(fn(): array => $this->roleManagementService->createRole($data), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensure($user, 'settings.view');

        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleRoleAction(fn(): array => $this->roleManagementService->updateRole($id, $data));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->permissionGuard->ensure($user, 'settings.view');

        return $this->handleRoleAction(
            function () use ($id): void {
                $this->roleManagementService->deleteRole($id);
            },
            Response::HTTP_NO_CONTENT,
        );
    }

    /** @param callable(): mixed $action */
    private function handleRoleAction(callable $action, int $successStatus = Response::HTTP_OK): JsonResponse
    {
        try {
            $result = $action();

            return $this->json(is_array($result) ? $result : null, $successStatus);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (ConflictHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (UnprocessableEntityHttpException $e) {
            $messages = array_values(array_filter(explode(' | ', $e->getMessage())));

            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
