<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

final class UserListingService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,pagination:array{total:int,page:int,perPage:int,totalPages:int}} */
    public function listUsers(string $search, int $page, int $perPage): array
    {
        $result = $this->userRepository->findPaginated($search, $page, $perPage);
        $items = array_map(fn(User $user): array => $this->serializeUserForList($user), $result['items']);

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

    /** @return array<string, mixed> */
    private function serializeUserForList(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
            'status'    => $user->getStatus(),
            'language'  => $user->getLanguage(),
            'createdAt' => $user->getCreatedAt()?->format('c'),
        ];
    }
}
