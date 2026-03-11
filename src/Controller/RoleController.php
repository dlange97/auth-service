<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RoleDefinition;
use App\Entity\User;
use App\Repository\RoleDefinitionRepository;
use App\Security\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth/roles', name: 'api_auth_roles_')]
class RoleController extends AbstractController
{
    public function __construct(
        private readonly RoleDefinitionRepository $roleRepo,
        private readonly PermissionService         $permissionService,
        private readonly ValidatorInterface        $validator,
        private readonly EntityManagerInterface    $em,
    ) {}

    /**
     * GET /api/auth/roles – list all role definitions (system + custom)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function listRoles(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->permissionService->userHasPermission($user, 'settings.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: settings.view'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(array_map($this->serialize(...), $this->roleRepo->findAllOrdered()));
    }

    /**
     * POST /api/auth/roles – create a new custom role
     * Body: { "name": "My Role", "slug": "ROLE_MY_ROLE", "permissions": ["dashboard.view", ...] }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->permissionService->userHasPermission($user, 'settings.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: settings.view'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $role = new RoleDefinition();
        $role->setIsSystem(false);

        return $this->applyAndSave($role, $data, Response::HTTP_CREATED);
    }

    /**
     * PUT /api/auth/roles/{id} – update a custom role (system roles: name only)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->permissionService->userHasPermission($user, 'settings.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: settings.view'], Response::HTTP_FORBIDDEN);
        }

        $role = $this->roleRepo->find($id);
        if (!$role) {
            return $this->json(['error' => 'Role not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // System roles: only allow renaming the display name
        if ($role->isSystem()) {
            if (!empty($data['name'])) {
                $role->setName($data['name']);
                $this->em->flush();
            }
            return $this->json($this->serialize($role));
        }

        return $this->applyAndSave($role, $data, Response::HTTP_OK);
    }

    /**
     * DELETE /api/auth/roles/{id} – delete a custom role (system roles cannot be deleted)
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->permissionService->userHasPermission($user, 'settings.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: settings.view'], Response::HTTP_FORBIDDEN);
        }

        $role = $this->roleRepo->find($id);
        if (!$role) {
            return $this->json(['error' => 'Role not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($role->isSystem()) {
            return $this->json(['error' => 'System roles cannot be deleted.'], Response::HTTP_CONFLICT);
        }

        $this->roleRepo->remove($role, true);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function applyAndSave(RoleDefinition $role, array $data, int $status): JsonResponse
    {
        if (isset($data['name'])) {
            $role->setName($data['name']);
        }
        if (isset($data['slug']) && !$role->isSystem()) {
            $slug = strtoupper(trim($data['slug']));
            if ($this->roleRepo->slugExists($slug, $role->getId())) {
                return $this->json(['error' => "A role with slug '{$slug}' already exists."], Response::HTTP_CONFLICT);
            }
            $role->setSlug($slug);
        }
        if (isset($data['permissions']) && is_array($data['permissions']) && !$role->isSystem()) {
            $allowed = $this->permissionService->getAllPermissions();
            $perms   = array_values(array_intersect($data['permissions'], $allowed));
            $role->setPermissions($perms);
        }

        $errors = $this->validator->validate($role);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $e) {
                $messages[] = $e->getPropertyPath() . ': ' . $e->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->roleRepo->save($role, true);
        return $this->json($this->serialize($role), $status);
    }

    private function serialize(RoleDefinition $role): array
    {
        return [
            'id'          => $role->getId(),
            'name'        => $role->getName(),
            'slug'        => $role->getSlug(),
            'permissions' => $role->getPermissions(),
            'isSystem'    => $role->isSystem(),
        ];
    }
}
