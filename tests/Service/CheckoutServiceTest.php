<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CheckoutInvite;
use App\Entity\Instance;
use App\Repository\CheckoutInviteRepository;
use App\Repository\InstanceRepository;
use App\Repository\UserRepository;
use App\Service\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CheckoutServiceTest extends TestCase
{
    private CheckoutInviteRepository&MockObject $inviteRepository;
    private InstanceRepository&MockObject $instanceRepository;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $em;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ValidatorInterface&MockObject $validator;
    private CheckoutService $service;

    protected function setUp(): void
    {
        $this->inviteRepository   = $this->createMock(CheckoutInviteRepository::class);
        $this->instanceRepository = $this->createMock(InstanceRepository::class);
        $this->userRepository     = $this->createMock(UserRepository::class);
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->jwtManager         = $this->createMock(JWTTokenManagerInterface::class);
        $this->passwordHasher     = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator          = $this->createMock(ValidatorInterface::class);

        $this->service = new CheckoutService(
            $this->inviteRepository,
            $this->instanceRepository,
            $this->userRepository,
            $this->em,
            $this->jwtManager,
            $this->passwordHasher,
            $this->validator,
        );
    }

    public function testFindValidInviteReturnsInviteWhenFound(): void
    {
        $invite = new CheckoutInvite();
        $this->inviteRepository->method('findUnusedByHash')->with('abc123')->willReturn($invite);

        $this->assertSame($invite, $this->service->findValidInvite('abc123'));
    }

    public function testFindValidInviteThrowsWhenNotFound(): void
    {
        $this->inviteRepository->method('findUnusedByHash')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid or already used invite link.');

        $this->service->findValidInvite('bad-hash');
    }

    public function testCompleteCheckoutThrowsOnMissingRequiredFields(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('instanceName, instanceSubdomain, adminEmail and adminPassword are required.');

        $this->service->completeCheckout(new CheckoutInvite(), []);
    }

    public function testCompleteCheckoutThrowsOnShortPassword(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters.');

        $this->service->completeCheckout(new CheckoutInvite(), [
            'instanceName'      => 'Acme',
            'instanceSubdomain' => 'acme',
            'adminEmail'        => 'admin@acme.com',
            'adminPassword'     => 'short',
        ]);
    }

    public function testCompleteCheckoutThrowsOnDuplicateSubdomain(): void
    {
        $this->instanceRepository
            ->method('findBySubdomain')
            ->with('acme')
            ->willReturn(new Instance());

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('This subdomain is already taken.');

        $this->service->completeCheckout(new CheckoutInvite(), [
            'instanceName'      => 'Acme',
            'instanceSubdomain' => 'acme',
            'adminEmail'        => 'admin@acme.com',
            'adminPassword'     => 'SecurePass123',
        ]);
    }

    public function testCompleteCheckoutThrowsOnDuplicateEmail(): void
    {
        $this->instanceRepository->method('findBySubdomain')->willReturn(null);
        $this->userRepository->method('findOneBy')->willReturn(new \App\Entity\User());

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $this->service->completeCheckout(new CheckoutInvite(), [
            'instanceName'      => 'Acme',
            'instanceSubdomain' => 'acme',
            'adminEmail'        => 'existing@acme.com',
            'adminPassword'     => 'SecurePass123',
        ]);
    }

    public function testCompleteCheckoutThrowsOnValidationFailure(): void
    {
        $this->instanceRepository->method('findBySubdomain')->willReturn(null);
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violation->method('getPropertyPath')->willReturn('subdomain');
        $violation->method('getMessage')->willReturn('Invalid format.');

        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service->completeCheckout(new CheckoutInvite(), [
            'instanceName'      => 'Acme',
            'instanceSubdomain' => 'INVALID!!',
            'adminEmail'        => 'admin@acme.com',
            'adminPassword'     => 'SecurePass123',
        ]);
    }

    public function testCompleteCheckoutSuccessfully(): void
    {
        $this->instanceRepository->method('findBySubdomain')->willReturn(null);
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->jwtManager->method('create')->willReturn('jwt.token.here');

        $this->em->expects($this->once())->method('beginTransaction');
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('commit');
        $this->em->expects($this->never())->method('rollback');

        $invite = new CheckoutInvite();
        $result = $this->service->completeCheckout($invite, [
            'instanceName'      => 'Acme Corp',
            'instanceSubdomain' => 'acme',
            'adminEmail'        => 'admin@acme.com',
            'adminPassword'     => 'SecurePass123',
            'adminFirstName'    => 'Jan',
            'adminLastName'     => 'Kowalski',
        ]);

        $this->assertSame('acme', $result['subdomain']);
        $this->assertSame('jwt.token.here', $result['token']);
        $this->assertNotEmpty($result['instanceId']);
        $this->assertTrue($invite->isUsed());
    }

    public function testCompleteCheckoutRollsBackOnPersistenceFailure(): void
    {
        $this->instanceRepository->method('findBySubdomain')->willReturn(null);
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->em->method('beginTransaction');
        $this->em->method('flush')->willThrowException(new \RuntimeException('DB error'));
        $this->em->expects($this->once())->method('rollback');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not complete checkout. Please try again.');

        $this->service->completeCheckout(new CheckoutInvite(), [
            'instanceName'      => 'Acme',
            'instanceSubdomain' => 'acme',
            'adminEmail'        => 'admin@acme.com',
            'adminPassword'     => 'SecurePass123',
        ]);
    }
}
