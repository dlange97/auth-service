<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RoleDefinition;
use App\Repository\RoleDefinitionRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RoleManagementService
{
    public function __construct(
        private readonly RoleDefinitionRepository $roleRepo,
        private readonly UserRepository $userRepository,
        private readonly PermissionService $permissionService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return list<array<string, int|string|bool|list<string>>> */
    public function listRoles(): array
    {
        return array_map($this->serialize(...), $this->roleRepo->findAllOrdered());
    }

    /** @param array<string, mixed> $data
     *  @return array<string, int|string|bool|list<string>>
     */
    public function createRole(array $data): array
    {
        $role = new RoleDefinition();
        $role->setIsSystem(false);

        return $this->applyAndSave($role, $data);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, int|string|bool|list<string>>
     */
    public function updateRole(int $id, array $data): array
    {
        $role = $this->roleRepo->find($id);
        if ($role === null) {
            throw new NotFoundHttpException('Role not found.');
        }

        if ($role->isSystem()) {
            if (!empty($data['name'])) {
                $role->setName((string) $data['name']);
                $this->roleRepo->save($role, true);
            }

            return $this->serialize($role);
        }

        return $this->applyAndSave($role, $data);
    }

    public function deleteRole(int $id): void
    {
        $role = $this->roleRepo->find($id);
        if ($role === null) {
            throw new NotFoundHttpException('Role not found.');
        }

        if ($role->isSystem()) {
            throw new ConflictHttpException('System roles cannot be deleted.');
        }

        if ($this->userRepository->countByRoleSlug($role->getSlug()) > 0) {
            throw new ConflictHttpException('Role cannot be deleted because it is assigned to users.');
        }

        $this->roleRepo->remove($role, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, int|string|bool|list<string>>
     */
    private function applyAndSave(RoleDefinition $role, array $data): array
    {
        if (isset($data['name'])) {
            $role->setName((string) $data['name']);
        }

        if (isset($data['slug']) && !$role->isSystem()) {
            $slug = strtoupper(trim((string) $data['slug']));
            if ($this->roleRepo->slugExists($slug, $role->getId())) {
                throw new ConflictHttpException(sprintf("A role with slug '%s' already exists.", $slug));
            }
            $role->setSlug($slug);
        }

        if (isset($data['permissions']) && is_array($data['permissions']) && !$role->isSystem()) {
            $allowed = $this->permissionService->getAllPermissions();
            $permissions = array_values(array_intersect($data['permissions'], $allowed));
            $role->setPermissions($permissions);
        }

        $errors = $this->validator->validate($role);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            throw new UnprocessableEntityHttpException(implode(' | ', $messages));
        }

        $this->roleRepo->save($role, true);

        return $this->serialize($role);
    }

    /** @return array<string, int|string|bool|list<string>> */
    private function serialize(RoleDefinition $role): array
    {
        return [
            'id' => $role->getId(),
            'name' => $role->getName(),
            'slug' => $role->getSlug(),
            'permissions' => $role->getPermissions(),
            'isSystem' => $role->isSystem(),
            'assignedUsersCount' => $this->userRepository->countByRoleSlug($role->getSlug()),
        ];
    }
}
