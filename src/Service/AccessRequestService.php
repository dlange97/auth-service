<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\PermissionService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AccessRequestService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionService $permissionService,
        private readonly NotificationGateway $notificationGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException|HttpException
     */
    public function sendRequest(array $data): void
    {
        $email     = trim((string) ($data['email'] ?? ''));
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName  = trim((string) ($data['lastName'] ?? ''));
        $message   = trim((string) ($data['message'] ?? ''));

        if ($email === '') {
            throw new BadRequestHttpException('Email is required.');
        }

        $recipients = $this->findEligibleRecipients();

        $ok = $this->notificationGateway->sendRequestAccess([
            'email'     => $email,
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'message'   => $message,
        ], $recipients);

        if (!$ok) {
            throw new HttpException(502, 'Unable to send access request at the moment.');
        }
    }

    /** @return list<array{id: string|null, email: string|null}> */
    private function findEligibleRecipients(): array
    {
        $recipients = [];
        foreach ($this->userRepository->findAllOrdered() as $user) {
            if (
                $this->permissionService->userHasPermission($user, 'users.create')
                || $this->permissionService->userHasPermission($user, 'users.assign_roles')
            ) {
                $recipients[] = [
                    'id'    => $user->getId(),
                    'email' => $user->getEmail(),
                ];
            }
        }

        return $recipients;
    }
}
