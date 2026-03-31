<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserRegistrationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService,
    ) {
    }

    /**
     * Register a new user (self-registration; default ROLE_USER).
     *
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException|ConflictHttpException|UnprocessableEntityHttpException
     */
    public function register(array $data): User
    {
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $this->validateCredentials($email, $password);
        $this->ensureEmailAvailable($email);

        $user = $this->buildUser($email, $password, $data['firstName'] ?? null, $data['lastName'] ?? null);
        $user->setRoles([PermissionService::ROLE_USER]);

        $this->validateEntity($user);
        $this->userRepository->save($user, true);

        return $user;
    }

    /**
     * Create a user with an explicit role (admin-initiated creation).
     *
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException|ConflictHttpException|UnprocessableEntityHttpException
     */
    public function createUser(array $data): User
    {
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? PermissionService::ROLE_USER;

        $this->validateCredentials($email, $password);

        if (!$this->permissionService->isRoleSupported($role)) {
            throw new BadRequestHttpException('Unsupported role.');
        }

        $this->ensureEmailAvailable($email);

        $user = $this->buildUser($email, $password, $data['firstName'] ?? null, $data['lastName'] ?? null);
        $user->setRoles([$role]);

        $this->validateEntity($user);
        $this->userRepository->save($user, true);

        return $user;
    }

    private function validateCredentials(string $email, string $password): void
    {
        if ($email === '' || $password === '') {
            throw new BadRequestHttpException('Email and password are required.');
        }

        if (strlen($password) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }
    }

    private function ensureEmailAvailable(string $email): void
    {
        if ($this->userRepository->findByEmail($email)) {
            throw new ConflictHttpException('An account with this email already exists.');
        }
    }

    private function buildUser(string $email, string $password, ?string $firstName, ?string $lastName): User
    {
        $user = new User();
        $user->setId(Uuid::uuid4()->toString());
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        return $user;
    }

    private function validateEntity(User $user): void
    {
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            throw new UnprocessableEntityHttpException(implode(' | ', $messages));
        }
    }
}
