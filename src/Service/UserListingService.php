<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;

final class UserListingService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserSerializer $userSerializer,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,pagination:array{total:int,page:int,perPage:int,totalPages:int}} */
    public function listUsers(string $search, int $page, int $perPage): array
    {
        $result = $this->userRepository->findPaginated($search, $page, $perPage);
        $items = array_map($this->userSerializer->serializeForList(...), $result['items']);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'totalPages' => $result['totalPages'],
            ],
        ];
    }
}
