<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\RoleDefinitionRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\PermissionGuard;
use App\Service\UserListingService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/auth', name: 'auth_manage_')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService,
        private readonly PermissionGuard $permissionGuard,
        private readonly UserListingService $userListingService,
        private readonly EntityManagerInterface $em,
        private readonly RoleDefinitionRepository $roleRepo,
    ) {
    }

    #[Route('/settings/access', name: 'settings_access', methods: ['GET'])]
    public function accessSettings(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'settings.view');

        return $this->json([
            'roles'           => $this->permissionService->getSupportedRoles(),
            'permissions'     => $this->permissionService->getAllPermissions(),
            'rolePermissions' => $this->permissionService->getRolePermissionsMap(),
            'roleDefinitions' => array_map(
                fn($r) => [
                    'id'                 => $r->getId(),
                    'name'               => $r->getName(),
                    'slug'               => $r->getSlug(),
                    'permissions'        => $r->getPermissions(),
                    'isSystem'           => $r->isSystem(),
                    'assignedUsersCount' => $this->userRepository->countByRoleSlug($r->getSlug()),
                ],
                $this->roleRepo->findAllOrdered(),
            ),
        ]);
    }

    #[Route('/users', name: 'users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.view');

        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 10)));

        return $this->json($this->userListingService->listUsers($search, $page, $perPage));
    }

    #[Route('/users/options', name: 'users_options', methods: ['GET'])]
    public function listUserOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 25)));

        $payload = $this->userListingService->listUsers($search, $page, $perPage);
        $items = array_values(array_filter(
            $payload['items'],
            static fn(array $user): bool => ($user['status'] ?? null) === User::STATUS_ACTIVE,
        ));

        return $this->json([
            'items' => array_map(
                static fn(array $user): array => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'firstName' => $user['firstName'],
                    'lastName' => $user['lastName'],
                ],
                $items,
            ),
            'pagination' => $payload['pagination'],
        ]);
    }

    #[Route('/users/exists/{id}', name: 'users_exists', methods: ['GET'])]
    public function userExists(string $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        return $this->json([
            'exists' => $user !== null && $user->getStatus() === User::STATUS_ACTIVE,
        ]);
    }

    #[Route('/users/{id}', name: 'users_show', methods: ['GET'])]
    public function showUser(string $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->permissionService->userHasPermission($currentUser, 'users.view')) {
            return $this->json(['error' => 'Forbidden. Missing permission: users.view'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->userRepository->findById($id);
        if (!$user) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeUser($user));
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
            'user' => $this->serializeUser($user),
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
                'status' => $user->getStatus(),
                'permissions' => $this->permissionService->getPermissionsForUser($user),
            ],
        ]);
    }

    #[Route('/users/{id}', name: 'users_update', methods: ['PATCH'])]
    public function updateUser(string $id, Request $request): JsonResponse
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

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '') {
                return $this->json(['error' => 'Email cannot be empty.'], Response::HTTP_BAD_REQUEST);
            }

            $existing = $this->userRepository->findByEmail($email);
            if ($existing && $existing->getId() !== $user->getId()) {
                return $this->json(['error' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
            }

            $user->setEmail($email);
        }

        if (array_key_exists('firstName', $data)) {
            $user->setFirstName($data['firstName'] !== null ? trim((string) $data['firstName']) : null);
        }

        if (array_key_exists('lastName', $data)) {
            $user->setLastName($data['lastName'] !== null ? trim((string) $data['lastName']) : null);
        }

        if (array_key_exists('role', $data)) {
            $role = (string) $data['role'];
            if (!$this->permissionService->isRoleSupported($role)) {
                return $this->json(['error' => 'Unsupported role.'], Response::HTTP_BAD_REQUEST);
            }
            $user->setRoles([$role]);
        }

        if (array_key_exists('language', $data)) {
            $lang = strtolower(trim((string) $data['language']));
            if (!in_array($lang, ['en', 'pl'], true)) {
                return $this->json(['error' => 'Unsupported language. Use: en, pl.'], Response::HTTP_BAD_REQUEST);
            }
            $user->setLanguage($lang);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json([
            'message' => 'User updated successfully.',
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/users/{id}', name: 'users_delete', methods: ['DELETE'])]
    public function softDeleteUser(string $id): JsonResponse
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

        if ($currentUser->getId() === $user->getId()) {
            return $this->json(['error' => 'You cannot deactivate your own account.'], Response::HTTP_CONFLICT);
        }

        $user->setStatus(User::STATUS_INACTIVE);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $user): array
    {
        return [
            'id'          => $user->getId(),
            'email'       => $user->getEmail(),
            'firstName'   => $user->getFirstName(),
            'lastName'    => $user->getLastName(),
            'roles'       => $user->getRoles(),
            'status'      => $user->getStatus(),
            'language'    => $user->getLanguage(),
            'dashboardLayout' => $user->getDashboardLayout(),
            'permissions' => $this->permissionService->getPermissionsForUser($user),
            'createdAt'   => $user->getCreatedAt()?->format('c'),
        ];
    }
}
