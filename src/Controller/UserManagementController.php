<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\RoleDefinitionRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_manage_')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService,
        private readonly EntityManagerInterface $em,
        private readonly RoleDefinitionRepository $roleRepo,
    ) {
    }

    #[Route('/settings/access', name: 'settings_access', methods: ['GET'])]
    public function accessSettings(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->permissionService->userHasPermission($currentUser, 'settings.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: settings.view'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'roles'           => $this->permissionService->getSupportedRoles(),
            'permissions'     => $this->permissionService->getAllPermissions(),
            'rolePermissions' => $this->permissionService->getRolePermissionsMap(),
            'roleDefinitions' => array_map(
                fn($r) => [
                    'id'          => $r->getId(),
                    'name'        => $r->getName(),
                    'slug'        => $r->getSlug(),
                    'permissions' => $r->getPermissions(),
                    'isSystem'    => $r->isSystem(),
                ],
                $this->roleRepo->findAllOrdered(),
            ),
        ]);
    }

    #[Route('/users', name: 'users_list', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->permissionService->userHasPermission($currentUser, 'users.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: users.view'], Response::HTTP_FORBIDDEN);
        }

        $users = $this->userRepository->findAllOrdered();
        $payload = array_map(function (User $user): array {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'permissions' => $this->permissionService->getPermissionsForUser($user),
                'createdAt' => $user->getCreatedAt()?->format('c'),
            ];
        }, $users);

        return $this->json($payload);
    }

    #[Route('/users', name: 'users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->permissionService->userHasPermission($currentUser, 'users.create')) {
            return $this->json(['error' => 'Forbidden. Missing permission: users.create'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        $role = $data['role'] ?? PermissionService::ROLE_USER;

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->permissionService->isRoleSupported($role)) {
            return $this->json(['error' => 'Unsupported role.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findByEmail($email)) {
            return $this->json(['error' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setId(Uuid::uuid4()->toString());
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'message' => 'User created successfully.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'permissions' => $this->permissionService->getPermissionsForUser($user),
                'createdAt' => $user->getCreatedAt()?->format('c'),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/users/{id}/role', name: 'users_assign_role', methods: ['PATCH'])]
    public function assignRole(string $id, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->permissionService->userHasPermission($currentUser, 'users.assign_roles')) {
            return $this->json(['error' => 'Forbidden. Missing permission: users.assign_roles'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->userRepository->findById($id);
        if (!$user) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $role = $data['role'] ?? null;

        if (!is_string($role) || !$this->permissionService->isRoleSupported($role)) {
            return $this->json(['error' => 'Unsupported role.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setRoles([$role]);
        $this->em->flush();

        return $this->json([
            'message' => 'Role assigned successfully.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'permissions' => $this->permissionService->getPermissionsForUser($user),
            ],
        ]);
    }
}
