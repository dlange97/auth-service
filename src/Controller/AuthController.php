<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use App\Service\NotificationGateway;
use App\Service\PermissionGuard;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Ramsey\Uuid\Uuid;

#[Route('/auth', name: 'auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService,
        private readonly NotificationGateway $notificationGateway,
        private readonly PermissionGuard $permissionGuard,
    ) {
    }
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.create');

        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findByEmail($email)) {
            return $this->json(['error' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $uuid = Uuid::uuid4()->toString();
        $user->setId($uuid);
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->userRepository->save($user, true);

        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'User registered successfully.',
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
    }
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['user' => $this->serializeUser($user)]);
    }
    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validateToken(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'valid' => true,
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/request-access', name: 'request_access', methods: ['POST'])]
    public function requestAccess(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($data['email'] ?? ''));
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($email === '') {
            return $this->json(['error' => 'Email is required.'], Response::HTTP_BAD_REQUEST);
        }

        $recipients = [];
        foreach ($this->userRepository->findAllOrdered() as $user) {
            if (
                $this->permissionService->userHasPermission($user, 'users.create')
                || $this->permissionService->userHasPermission($user, 'users.assign_roles')
            ) {
                $recipients[] = [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ];
            }
        }

        $ok = $this->notificationGateway->sendRequestAccess([
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'message' => $message,
        ], $recipients);

        if (!$ok) {
            return $this->json(['error' => 'Unable to send access request at the moment.'], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'message' => 'Access request sent successfully.',
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/me', name: 'me_update', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('language', $data)) {
            $lang = strtolower(trim((string) $data['language']));
            if (!in_array($lang, ['en', 'pl'], true)) {
                return $this->json(['error' => 'Unsupported language. Use: en, pl.'], Response::HTTP_BAD_REQUEST);
            }
            $user->setLanguage($lang);
        }

        if (array_key_exists('firstName', $data)) {
            $user->setFirstName($data['firstName'] !== null ? trim((string) $data['firstName']) : null);
        }

        if (array_key_exists('lastName', $data)) {
            $user->setLastName($data['lastName'] !== null ? trim((string) $data['lastName']) : null);
        }

        if (array_key_exists('dashboardLayout', $data)) {
            $normalizedLayout = $this->normalizeDashboardLayout($data['dashboardLayout']);
            if ($normalizedLayout === false) {
                return $this->json(['error' => 'Invalid dashboardLayout payload.'], Response::HTTP_BAD_REQUEST);
            }

            $user->setDashboardLayout($normalizedLayout);
        }

        $this->userRepository->save($user, true);

        return $this->json(['user' => $this->serializeUser($user)]);
    }

    /**
     * @return array{
     *   id: string|null,
     *   email: string|null,
     *   firstName: string|null,
     *   lastName: string|null,
     *   roles: list<string>,
     *   status: string,
     *   language: string,
    *   dashboardLayout: array<string,mixed>|null,
     *   permissions: list<string>,
     *   createdAt: string|null
     * }
     */
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

    /**
     * @param mixed $payload
     * @return array<string, mixed>|null|false
     */
    private function normalizeDashboardLayout(mixed $payload): array|null|false
    {
        if ($payload === null) {
            return null;
        }

        if (!is_array($payload)) {
            return false;
        }

        $order = $payload['order'] ?? [];
        $scales = $payload['scales'] ?? [];

        if (!is_array($order) || !is_array($scales)) {
            return false;
        }

        $normalizedOrder = [];
        foreach ($order as $id) {
            if (!is_string($id)) {
                return false;
            }

            $trimmed = trim($id);
            if ($trimmed === '') {
                continue;
            }

            $normalizedOrder[] = $trimmed;
        }

        /** @var array<string, array{x: float, y: float}> $normalizedScales */
        $normalizedScales = [];
        foreach ($scales as $tileId => $scale) {
            if (!is_string($tileId) || trim($tileId) === '') {
                return false;
            }

            if (is_numeric($scale)) {
                $value = (float) $scale;
                if ($value < 0.5 || $value > 1.5) {
                    return false;
                }

                $normalizedScales[trim($tileId)] = [
                    'x' => round($value, 3),
                    'y' => round($value, 3),
                ];

                continue;
            }

            if (!is_array($scale)) {
                return false;
            }

            $x = $scale['x'] ?? null;
            $y = $scale['y'] ?? null;

            if (!is_numeric($x) || !is_numeric($y)) {
                return false;
            }

            $xValue = (float) $x;
            $yValue = (float) $y;
            if ($xValue < 0.5 || $xValue > 1.5 || $yValue < 0.5 || $yValue > 1.5) {
                return false;
            }

            $normalizedScales[trim($tileId)] = [
                'x' => round($xValue, 3),
                'y' => round($yValue, 3),
            ];
        }

        return [
            'order' => array_values(array_unique($normalizedOrder)),
            'scales' => $normalizedScales,
        ];
    }
}
