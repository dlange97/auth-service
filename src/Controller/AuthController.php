<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AccessRequestService;
use App\Service\PermissionGuard;
use App\Service\Input\RequestJsonPayloadResolver;
use App\Service\UserProfileService;
use App\Service\UserRegistrationService;
use App\Service\UserSerializer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly PermissionGuard $permissionGuard,
        private readonly UserRegistrationService $registrationService,
        private readonly UserProfileService $profileService,
        private readonly AccessRequestService $accessRequestService,
        private readonly UserSerializer $userSerializer,
        private readonly RequestJsonPayloadResolver $payloadResolver,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $this->permissionGuard->ensure($currentUser, 'users.create');

        $data = $this->payloadResolver->resolve($request);
        $user = $this->registrationService->register($data);
        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'User registered successfully.',
            'token'   => $token,
            'user'    => $this->userSerializer->serialize($user),
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

        return $this->json(['user' => $this->userSerializer->serialize($user)]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validateToken(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'valid' => true,
            'user'  => $this->userSerializer->serialize($user),
        ]);
    }

    #[Route('/request-access', name: 'request_access', methods: ['POST'])]
    public function requestAccess(Request $request): JsonResponse
    {
        $data = $this->payloadResolver->resolve($request);
        $this->accessRequestService->sendRequest($data);

        return $this->json([
            'message' => 'Access request sent successfully.',
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/me', name: 'me_update', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->payloadResolver->resolve($request);

        $this->profileService->updateProfile($user, $data);

        return $this->json(['user' => $this->userSerializer->serialize($user)]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie(
            Cookie::create('jwt_token')
                ->withValue('')
                ->withExpires(new \DateTimeImmutable('1970-01-01'))
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
        return $response;
    }
}
