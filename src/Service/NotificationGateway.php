<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NotificationGateway
{
    public function __construct(
        #[Autowire('%env(string:NOTIFICATION_SERVICE_URL)%')]
        private readonly string $serviceUrl,
        #[Autowire('%env(string:INTERNAL_NOTIFICATION_TOKEN)%')]
        private readonly string $internalToken,
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

        $url = rtrim($this->serviceUrl, '/') . '/api/notifications/internal/request-access';
        $payload = json_encode([
            'requester' => $requester,
            'recipients' => $recipients,
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n"
                    . 'X-Internal-Token: ' . $this->internalToken . "\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false || !isset($http_response_header) || !is_array($http_response_header)) {
            return false;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            return false;
        }

        $statusCode = (int) $matches[1];
        return $statusCode >= 200 && $statusCode < 300;
    }
}
