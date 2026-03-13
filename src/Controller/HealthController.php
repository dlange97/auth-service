<?php

declare(strict_types=1);

namespace App\Controller;

use MyDashboard\Shared\Controller\HealthResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    use HealthResponseTrait;

    #[Route('/auth/health', name: 'auth_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json($this->createHealthPayload('auth-service'));
    }
}
