<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DashboardLayoutNormalizer;
use App\Service\Locale\LanguagePolicy;
use App\Service\UserProfileService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UserProfileServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private DashboardLayoutNormalizer $layoutNormalizer;
    private UserProfileService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->layoutNormalizer = new DashboardLayoutNormalizer();

        $this->service = new UserProfileService(
            $this->userRepository,
            $this->layoutNormalizer,
            new LanguagePolicy(),
        );
    }

    public function testUpdateLanguage(): void
    {
        $user = $this->makeUser();
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, ['language' => 'pl']);

        $this->assertSame('pl', $user->getLanguage());
    }

    public function testUpdateLanguageThrowsOnUnsupported(): void
    {
        $user = $this->makeUser();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported language');

        $this->service->updateProfile($user, ['language' => 'de']);
    }

    public function testUpdateFirstName(): void
    {
        $user = $this->makeUser();
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, ['firstName' => ' John ']);

        $this->assertSame('John', $user->getFirstName());
    }

    public function testUpdateFirstNameNull(): void
    {
        $user = $this->makeUser();
        $user->setFirstName('Existing');
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, ['firstName' => null]);

        $this->assertNull($user->getFirstName());
    }

    public function testUpdateLastName(): void
    {
        $user = $this->makeUser();
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, ['lastName' => 'Doe']);

        $this->assertSame('Doe', $user->getLastName());
    }

    public function testUpdateDashboardLayout(): void
    {
        $user = $this->makeUser();
        $layout = ['order' => ['a'], 'scales' => []];
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, ['dashboardLayout' => $layout]);

        $this->assertSame($layout, $user->getDashboardLayout());
    }

    public function testUpdateDashboardLayoutThrowsOnInvalid(): void
    {
        $user = $this->makeUser();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid dashboardLayout payload.');

        $this->service->updateProfile($user, ['dashboardLayout' => 'bad-string']);
    }

    public function testUpdateDashboardLayoutNullIsAccepted(): void
    {
        $user = $this->makeUser();
        $user->setDashboardLayout(['old' => 'data']);
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, ['dashboardLayout' => null]);

        $this->assertNull($user->getDashboardLayout());
    }

    public function testUpdateMultipleFields(): void
    {
        $user = $this->makeUser();
        $this->userRepository->expects($this->once())->method('save');

        $this->service->updateProfile($user, [
            'language'  => 'en',
            'firstName' => 'Jane',
            'lastName'  => 'Smith',
        ]);

        $this->assertSame('en', $user->getLanguage());
        $this->assertSame('Jane', $user->getFirstName());
        $this->assertSame('Smith', $user->getLastName());
    }

    public function testNoDataStillSaves(): void
    {
        $user = $this->makeUser();
        $this->userRepository->expects($this->once())->method('save');

        $result = $this->service->updateProfile($user, []);

        $this->assertSame($user, $result);
    }

    private function makeUser(): User
    {
        return (new User())
            ->setId('user-1')
            ->setEmail('test@example.com')
            ->setRoles(['ROLE_USER']);
    }
}
