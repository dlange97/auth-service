<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CheckoutInvite;
use App\Entity\Instance;
use App\Entity\User;
use App\Repository\CheckoutInviteRepository;
use App\Repository\InstanceRepository;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CheckoutService
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

    /**
     * @throws NotFoundHttpException when the hash is invalid or already used
     */
    public function findValidInvite(string $hash): CheckoutInvite
    {
        $invite = $this->checkoutInviteRepository->findUnusedByHash($hash);

        if ($invite === null) {
            throw new NotFoundHttpException('Invalid or already used invite link.');
        }

        return $invite;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{instanceId: string, subdomain: string, token: string}
     *
     * @throws BadRequestHttpException
     * @throws ConflictHttpException
     * @throws UnprocessableEntityHttpException
     * @throws \RuntimeException on persistence failure
     */
    public function completeCheckout(CheckoutInvite $invite, array $data): array
    {
        $instanceName      = trim((string) ($data['instanceName'] ?? ''));
        $instanceSubdomain = strtolower(trim((string) ($data['instanceSubdomain'] ?? '')));
        $adminEmail        = trim((string) ($data['adminEmail'] ?? ''));
        $adminPassword     = (string) ($data['adminPassword'] ?? '');
        $adminFirstName    = trim((string) ($data['adminFirstName'] ?? '')) ?: null;
        $adminLastName     = trim((string) ($data['adminLastName'] ?? '')) ?: null;

        if ($instanceName === '' || $instanceSubdomain === '' || $adminEmail === '' || $adminPassword === '') {
            throw new BadRequestHttpException(
                'instanceName, instanceSubdomain, adminEmail and adminPassword are required.',
            );
        }

        if (strlen($adminPassword) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }

        if ($this->instanceRepository->findBySubdomain($instanceSubdomain) !== null) {
            throw new ConflictHttpException('This subdomain is already taken.');
        }

        if ($this->userRepository->findOneBy(['email' => $adminEmail]) !== null) {
            throw new ConflictHttpException('An account with this email already exists.');
        }

        $instance = new Instance();
        $instance->setId(Uuid::uuid4()->toString());
        $instance->setName($instanceName);
        $instance->setSubdomain($instanceSubdomain);
        $this->validateEntity($instance);

        $user = new User();
        $user->setId(Uuid::uuid4()->toString());
        $user->setEmail($adminEmail);
        $user->setFirstName($adminFirstName);
        $user->setLastName($adminLastName);
        $user->setRoles([PermissionService::ROLE_ADMIN]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $adminPassword));
        $user->setInstanceId($instance->getId());
        $user->addInstance($instance);
        $this->validateEntity($user);

        $this->em->beginTransaction();
        try {
            $this->em->persist($instance);
            $this->em->persist($user);
            $invite->markUsed();
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new \RuntimeException('Could not complete checkout. Please try again.', 0, $e);
        }

        return [
            'instanceId' => (string) $instance->getId(),
            'subdomain'  => $instance->getSubdomain(),
            'token'      => $this->jwtManager->create($user),
        ];
    }

    private function validateEntity(object $entity): void
    {
        $errors = $this->validator->validate($entity);

        if (count($errors) === 0) {
            return;
        }

        $messages = [];
        foreach ($errors as $err) {
            $messages[] = $err->getPropertyPath() . ': ' . $err->getMessage();
        }

        throw new UnprocessableEntityHttpException(implode(' | ', $messages));
    }
}
