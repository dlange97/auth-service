<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Instance;
use App\Entity\User;
use App\Repository\CheckoutInviteRepository;
use App\Repository\InstanceRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/auth/checkout', name: 'auth_checkout_')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CheckoutInviteRepository $checkoutInviteRepository,
        private readonly InstanceRepository $instanceRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** Validate that an invite hash is still usable (GET, public). */
    #[Route('/{hash}/validate', name: 'validate', methods: ['GET'])]
    public function validate(string $hash): JsonResponse
    {
        $invite = $this->checkoutInviteRepository->findUnusedByHash($hash);

        if ($invite === null) {
            return $this->json(['valid' => false, 'reason' => 'Invalid or already used invite link.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['valid' => true]);
    }

    /**
     * Complete the checkout: create an Instance + admin User, consume the invite,
     * and return a JWT for the new admin (POST, public).
     *
     * Payload:
     *   instanceName      string  required
     *   instanceSubdomain string  required  (slug, e.g. "acme")
     *   adminEmail        string  required
     *   adminPassword     string  required (min 8 chars)
     *   adminFirstName    string  optional
     *   adminLastName     string  optional
     */
    #[Route('/{hash}', name: 'complete', methods: ['POST'])]
    public function complete(string $hash, Request $request): JsonResponse
    {
        $invite = $this->checkoutInviteRepository->findUnusedByHash($hash);
        if ($invite === null) {
            return $this->json(['error' => 'Invalid or already used invite link.'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $instanceName      = trim((string) ($data['instanceName'] ?? ''));
        $instanceSubdomain = strtolower(trim((string) ($data['instanceSubdomain'] ?? '')));
        $adminEmail        = trim((string) ($data['adminEmail'] ?? ''));
        $adminPassword     = (string) ($data['adminPassword'] ?? '');
        $adminFirstName    = trim((string) ($data['adminFirstName'] ?? '')) ?: null;
        $adminLastName     = trim((string) ($data['adminLastName'] ?? '')) ?: null;

        if ($instanceName === '' || $instanceSubdomain === '' || $adminEmail === '' || $adminPassword === '') {
            return $this->json(['error' => 'instanceName, instanceSubdomain, adminEmail and adminPassword are required.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($adminPassword) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->instanceRepository->findBySubdomain($instanceSubdomain) !== null) {
            return $this->json(['error' => 'This subdomain is already taken.'], Response::HTTP_CONFLICT);
        }

        if ($this->userRepository->findOneBy(['email' => $adminEmail]) !== null) {
            return $this->json(['error' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        // Create Instance
        $instance = new Instance();
        $instance->setId(Uuid::uuid4()->toString());
        $instance->setName($instanceName);
        $instance->setSubdomain($instanceSubdomain);

        $instanceErrors = $this->validator->validate($instance);
        if (count($instanceErrors) > 0) {
            $messages = [];
            foreach ($instanceErrors as $err) {
                $messages[] = $err->getPropertyPath() . ': ' . $err->getMessage();
            }
            return $this->json(['error' => implode(' | ', $messages)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create admin User
        $user = new User();
        $user->setId(Uuid::uuid4()->toString());
        $user->setEmail($adminEmail);
        $user->setFirstName($adminFirstName);
        $user->setLastName($adminLastName);
        $user->setRoles([PermissionService::ROLE_ADMIN]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $adminPassword));
        $user->setInstanceId($instance->getId());
        $user->addInstance($instance);

        $userErrors = $this->validator->validate($user);
        if (count($userErrors) > 0) {
            $messages = [];
            foreach ($userErrors as $err) {
                $messages[] = $err->getPropertyPath() . ': ' . $err->getMessage();
            }
            return $this->json(['error' => implode(' | ', $messages)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Persist everything in one transaction
        $this->em->beginTransaction();
        try {
            $this->em->persist($instance);
            $this->em->persist($user);
            $invite->markUsed();
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            return $this->json(['error' => 'Could not complete checkout. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $token = $this->jwtManager->create($user);

        return $this->json([
            'message'    => 'Instance created successfully.',
            'instanceId' => $instance->getId(),
            'subdomain'  => $instance->getSubdomain(),
            'token'      => $token,
        ], Response::HTTP_CREATED);
    }
}
