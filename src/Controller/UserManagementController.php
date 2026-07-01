<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\RoleDefinitionRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\PermissionGuard;
use App\Service\NotificationGateway;
use App\Service\InviteService;
use App\Service\UserListingService;
use App\Service\UserManagementService;
use App\Service\UserRegistrationService;
use App\Service\UserSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'auth_manage_')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly PermissionGuard $permissionGuard,
        private readonly UserListingService $userListingService,
        private readonly UserRegistrationService $registrationService,
        private readonly UserManagementService $managementService,
        private readonly NotificationGateway $notificationGateway,
        private readonly InviteService $inviteService,
        private readonly UserSerializer $userSerializer,
        private readonly PermissionService $permissionService,
        private readonly UserRepository $userRepository,
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

        $search  = trim((string) $request->query->get('search', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 10)));

        return $this->json($this->userListingService->listUsers($search, $page, $perPage));
    }

    #[Route('/users/options', name: 'users_options', methods: ['GET'])]
    public function listUserOptions(Request $request): JsonResponse
    {
        $search  = trim((string) $request->query->get('search', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 25)));

        $payload = $this->userListingService->listUsers($search, $page, $perPage);
        $items = array_values(array_filter(
            $payload['items'],
            static fn(array $user): bool => ($user['status'] ?? null) === User::STATUS_ACTIVE,
        ));

        return $this->json([
            'items' => array_map(
                static fn(array $user): array => [
                    'id'        => $user['id'],
                    'email'     => $user['email'],
                    'firstName' => $user['firstName'],
                    'lastName'  => $user['lastName'],
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
        $this->permissionGuard->ensure($currentUser, 'users.view');

        $user = $this->managementService->findOrFail($id);

        return $this->json($this->userSerializer->serialize($user));
    }

    #[Route('/users', name: 'users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.create');

        $data = json_decode($request->getContent(), true) ?? [];
        $inviteUser = filter_var($data['inviteUser'] ?? false, FILTER_VALIDATE_BOOL);
        $inviteNotificationSent = false;
        $inviteReference = null;

        if ($inviteUser) {
            $user = $this->registrationService->createInvitedUser($data);

            $invite          = $this->inviteService->createInvite($user);
            $inviteReference = $invite['invite']->getReference();

            $inviteNotificationSent = $this->notificationGateway->sendUserInvitation([
                'recipientUserId' => $user->getId(),
                'recipientEmail' => (string) $user->getEmail(),
                'invitedUserEmail' => (string) $user->getEmail(),
                'inviteReference' => $inviteReference,
                'inviteLink' => $invite['link'],
                'invitedBy' => [
                    'userId' => $currentUser->getId(),
                    'email' => (string) $currentUser->getEmail(),
                    'firstName' => (string) ($currentUser->getFirstName() ?? ''),
                    'lastName' => (string) ($currentUser->getLastName() ?? ''),
                ],
            ]);
        } else {
            $user = $this->registrationService->createUser($data);
        }

        return $this->json([
            'message' => 'User created successfully.',
            'inviteRequested' => $inviteUser,
            'inviteReference' => $inviteReference,
            'inviteNotificationSent' => $inviteNotificationSent,
            'user'    => $this->userSerializer->serialize($user),
        ], Response::HTTP_CREATED);
    }

    #[Route('/users/{id}/role', name: 'users_assign_role', methods: ['PATCH'])]
    public function assignRole(string $id, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.assign_roles');

        $user = $this->managementService->findOrFail($id);
        $data = json_decode($request->getContent(), true) ?? [];
        $role = $data['role'] ?? null;

        if (!is_string($role)) {
            return $this->json(['error' => 'Unsupported role.'], Response::HTTP_BAD_REQUEST);
        }

        $this->managementService->assignRole($user, $role);

        return $this->json([
            'message' => 'Role assigned successfully.',
            'user'    => [
                'id'          => $user->getId(),
                'email'       => $user->getEmail(),
                'roles'       => $user->getRoles(),
                'status'      => $user->getStatus(),
                'permissions' => $this->permissionService->getPermissionsForUser($user),
            ],
        ]);
    }

    #[Route('/users/{id}', name: 'users_update', methods: ['PATCH'])]
    public function updateUser(string $id, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.assign_roles');

        $user = $this->managementService->findOrFail($id);
        $data = json_decode($request->getContent(), true) ?? [];

        $this->managementService->updateUser($user, $data);

        return $this->json([
            'message' => 'User updated successfully.',
            'user'    => $this->userSerializer->serialize($user),
        ]);
    }

    #[Route('/users/{id}', name: 'users_delete', methods: ['DELETE'])]
    public function softDeleteUser(string $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.assign_roles');

        $user = $this->managementService->findOrFail($id);
        $this->managementService->softDelete($user, $currentUser);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
