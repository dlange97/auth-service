<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserManagementService
{
    private const SUPPORTED_LANGUAGES = ['en', 'pl'];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionService $permissionService,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findOrFail(string $id): User
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found.');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException|ConflictHttpException|UnprocessableEntityHttpException
     */
    public function updateUser(User $user, array $data): User
    {
        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '') {
                throw new BadRequestHttpException('Email cannot be empty.');
            }

            $existing = $this->userRepository->findByEmail($email);
            if ($existing && $existing->getId() !== $user->getId()) {
                throw new ConflictHttpException('An account with this email already exists.');
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
                throw new BadRequestHttpException('Unsupported role.');
            }
            $user->setRoles([$role]);
        }

        if (array_key_exists('language', $data)) {
            $lang = strtolower(trim((string) $data['language']));
            if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
                throw new BadRequestHttpException('Unsupported language. Use: en, pl.');
            }
            $user->setLanguage($lang);
        }

        $this->validateEntity($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @throws BadRequestHttpException
     */
    public function assignRole(User $user, string $role): User
    {
        if (!$this->permissionService->isRoleSupported($role)) {
            throw new BadRequestHttpException('Unsupported role.');
        }

        $user->setRoles([$role]);
        $this->em->flush();

        return $user;
    }

    /**
     * @throws ConflictHttpException
     */
    public function softDelete(User $user, User $currentUser): void
    {
        if ($currentUser->getId() === $user->getId()) {
            throw new ConflictHttpException('You cannot deactivate your own account.');
        }

        $user->setStatus(User::STATUS_INACTIVE);
        $this->em->flush();
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
