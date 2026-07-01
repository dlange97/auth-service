<?php

declare(strict_types=1);

namespace App\Service;

use MyDashboard\Shared\Message\RequestAccessNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationGateway
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly string $notificationServiceUrl,
        private readonly string $internalNotificationToken,
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

    /** @param array<string, mixed> $payload */
    public function sendUserInvitation(array $payload): bool
    {
        return $this->postInternal('/notification/internal/user-invited', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function postInternal(string $path, array $payload): bool
    {
        $baseUrl = rtrim($this->notificationServiceUrl, '/');
        if ($baseUrl === '' || trim($this->internalNotificationToken) === '') {
            return false;
        }

        try {
            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

            $ch = curl_init($baseUrl . $path);
            if ($ch === false) {
                return false;
            }

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Internal-Token: ' . $this->internalNotificationToken,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
            ]);

            curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Throwable) {
            return false;
        }
    }
}
