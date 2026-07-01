<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\UserInvite;
use App\Repository\UserInviteRepository;
use App\Repository\UserRepository;
use App\Service\InviteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InviteServiceTest extends TestCase
{
    private UserInviteRepository&MockObject $inviteRepository;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $em;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private InviteService $service;

    protected function setUp(): void
    {
        $this->inviteRepository = $this->createMock(UserInviteRepository::class);
        $this->userRepository   = $this->createMock(UserRepository::class);
        $this->em               = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher   = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new InviteService(
            $this->inviteRepository,
            $this->userRepository,
            $this->em,
            $this->passwordHasher,
            'http://app.example.test',
        );
    }

    public function testCreateInviteGeneratesSecureTokenReferenceAndLink(): void
    {
        $user = (new User())->setId('user-1')->setEmail('invitee@example.com');

        $this->inviteRepository->expects($this->once())->method('save');

        $result = $this->service->createInvite($user);

        $this->assertInstanceOf(UserInvite::class, $result['invite']);
        // 32 random bytes => 64 hex characters.
        $this->assertSame(64, strlen($result['rawToken']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['rawToken']);
        $this->assertSame(
            'http://app.example.test/set-password/' . $result['rawToken'],
            $result['link'],
        );
        // The raw token must never be stored directly.
        $this->assertNotSame($result['rawToken'], $result['invite']->getTokenHash());
        $this->assertSame(
            hash('sha256', $result['rawToken']),
            $result['invite']->getTokenHash(),
        );
        $this->assertNotSame('', $result['invite']->getReference());
        $this->assertSame('user-1', $result['invite']->getUserId());
        $this->assertSame(UserInvite::STATUS_PENDING, $result['invite']->getStatus());
    }

    public function testFindUsableInviteReturnsNullForUnknownToken(): void
    {
        $this->inviteRepository->method('findByTokenHash')->willReturn(null);

        $this->assertNull($this->service->findUsableInvite('whatever'));
    }

    public function testFindUsableInviteReturnsNullForEmptyToken(): void
    {
        $this->inviteRepository->expects($this->never())->method('findByTokenHash');

        $this->assertNull($this->service->findUsableInvite(''));
    }

    public function testFindUsableInviteMarksExpiredInviteAndReturnsNull(): void
    {
        $invite = (new UserInvite())
            ->setUserId('user-1')
            ->setEmail('invitee@example.com')
            ->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        $this->inviteRepository->method('findByTokenHash')->willReturn($invite);
        $this->inviteRepository->expects($this->once())->method('save');

        $this->assertNull($this->service->findUsableInvite('token'));
        $this->assertSame(UserInvite::STATUS_EXPIRED, $invite->getStatus());
    }

    public function testFindUsableInviteReturnsNullForAlreadyAcceptedInvite(): void
    {
        $invite = (new UserInvite())->setEmail('invitee@example.com');
        $invite->markAccepted();

        $this->inviteRepository->method('findByTokenHash')->willReturn($invite);

        $this->assertNull($this->service->findUsableInvite('token'));
    }

    public function testAcceptInviteSetsPasswordAndActivatesUser(): void
    {
        $invite = (new UserInvite())
            ->setUserId('user-1')
            ->setEmail('invitee@example.com');

        $user = (new User())
            ->setId('user-1')
            ->setEmail('invitee@example.com')
            ->setStatus(User::STATUS_INVITED);

        $this->inviteRepository->method('findByTokenHash')->willReturn($invite);
        $this->userRepository->method('findById')->with('user-1')->willReturn($user);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-secret');

        $this->em->expects($this->once())->method('beginTransaction');
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('commit');

        $result = $this->service->acceptInvite('token', 'SecurePass123');

        $this->assertSame('hashed-secret', $result->getPassword());
        $this->assertSame(User::STATUS_ACTIVE, $result->getStatus());
        $this->assertSame(UserInvite::STATUS_ACCEPTED, $invite->getStatus());
        $this->assertNotNull($invite->getAcceptedAt());
    }

    public function testAcceptInviteThrowsOnInvalidToken(): void
    {
        $this->inviteRepository->method('findByTokenHash')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->acceptInvite('token', 'SecurePass123');
    }

    public function testAcceptInviteThrowsOnShortPassword(): void
    {
        $invite = (new UserInvite())->setUserId('user-1')->setEmail('invitee@example.com');
        $this->inviteRepository->method('findByTokenHash')->willReturn($invite);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters.');

        $this->service->acceptInvite('token', 'short');
    }

    public function testAcceptInviteThrowsWhenUserMissing(): void
    {
        $invite = (new UserInvite())->setUserId('ghost')->setEmail('invitee@example.com');
        $this->inviteRepository->method('findByTokenHash')->willReturn($invite);
        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->acceptInvite('token', 'SecurePass123');
    }
}
