<?php

declare(strict_types=1);

namespace App\Service\Locale;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class LanguagePolicy
{
    /** @var list<string> */
    private const SUPPORTED_LANGUAGES = ['en', 'pl'];

    public function normalizeOrFail(mixed $value): string
    {
        $lang = strtolower(trim((string) $value));
        if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
            throw new BadRequestHttpException(sprintf(
                'Unsupported language. Use: %s.',
                implode(', ', self::SUPPORTED_LANGUAGES),
            ));
        }

        return $lang;
    }
}
