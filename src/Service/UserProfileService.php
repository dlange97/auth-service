<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Locale\LanguagePolicy;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UserProfileService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DashboardLayoutNormalizer $layoutNormalizer,
        private readonly LanguagePolicy $languagePolicy,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    public function updateProfile(User $user, array $data): User
    {
        if (array_key_exists('language', $data)) {
            $lang = $this->languagePolicy->normalizeOrFail($data['language']);
            $user->setLanguage($lang);
        }

        if (array_key_exists('firstName', $data)) {
            $user->setFirstName($data['firstName'] !== null ? trim((string) $data['firstName']) : null);
        }

        if (array_key_exists('lastName', $data)) {
            $user->setLastName($data['lastName'] !== null ? trim((string) $data['lastName']) : null);
        }

        if (array_key_exists('dashboardLayout', $data)) {
            $normalizedLayout = $this->layoutNormalizer->normalize($data['dashboardLayout']);
            if ($normalizedLayout === false) {
                throw new BadRequestHttpException('Invalid dashboardLayout payload.');
            }
            $user->setDashboardLayout($normalizedLayout);
        }

        $this->userRepository->save($user, true);

        return $user;
    }
}
