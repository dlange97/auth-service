<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserInvite;
use App\Repository\UserInviteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InviteService
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserInviteRepository $inviteRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $appBaseUrl,
    ) {
    }

    /**
     * @return array{invite: UserInvite, rawToken: string, link: string}
     */
    public function createInvite(User $user): array
    {
        $rawToken = bin2hex(random_bytes(32));

        $invite = new UserInvite();
        $invite->setReference(Uuid::uuid4()->toString());
        $invite->setTokenHash($this->hashToken($rawToken));
        $invite->setUserId((string) $user->getId());
        $invite->setEmail((string) $user->getEmail());

        $this->inviteRepository->save($invite, true);

        return [
            'invite'   => $invite,
            'rawToken' => $rawToken,
            'link'     => $this->buildInviteLink($rawToken),
        ];
    }

    public function buildInviteLink(string $rawToken): string
    {
        return rtrim($this->appBaseUrl, '/') . '/set-password/' . $rawToken;
    }

    public function findUsableInvite(string $rawToken): ?UserInvite
    {
        if ($rawToken === '') {
            return null;
        }

        $invite = $this->inviteRepository->findByTokenHash($this->hashToken($rawToken));

        if ($invite === null || !$invite->isPending()) {
            return null;
        }

        if ($invite->isExpired()) {
            $invite->markExpired();
            $this->inviteRepository->save($invite, true);
            return null;
        }

        return $invite;
    }

    /**
     * @throws NotFoundHttpException when the token is invalid, used or expired
     * @throws BadRequestHttpException when the password is too weak
     * @throws \RuntimeException on persistence failure
     */
    public function acceptInvite(string $rawToken, string $password): User
    {
        $invite = $this->findUsableInvite($rawToken);

        if ($invite === null) {
            throw new NotFoundHttpException('This invitation link is invalid or has already been used.');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }

        $user = $this->userRepository->findById($invite->getUserId());

        if ($user === null) {
            throw new NotFoundHttpException('This invitation link is invalid or has already been used.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setStatus(User::STATUS_ACTIVE);
        $invite->markAccepted();

        $this->em->beginTransaction();
        try {
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new \RuntimeException('Could not complete the invitation. Please try again.', 0, $e);
        }

        return $user;
    }

    private function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
