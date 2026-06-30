<?php

declare(strict_types=1);

namespace App\Service\Input;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RequestJsonPayloadResolver
{
    /** @return array<string, mixed> */
    public function resolve(Request $request): array
    {
        $content = trim($request->getContent());
        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        if (!is_array($decoded)) {
            throw new BadRequestHttpException('JSON payload must be an object.');
        }

        return $decoded;
    }
}
