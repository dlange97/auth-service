<?php

declare(strict_types=1);

namespace App\Service;

use MyDashboard\Shared\Message\RequestAccessNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationGateway
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param array<string, mixed> $requester
     * @param array<int, array{id:string,email:string}> $recipients
     */
    public function sendRequestAccess(array $requester, array $recipients): bool
    {
        if (count($recipients) === 0) {
            return true;
        }

        try {
            $this->messageBus->dispatch(new RequestAccessNotificationMessage($requester, $recipients));
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
