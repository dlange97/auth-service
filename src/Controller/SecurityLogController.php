<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SecurityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/admin/security-log', name: 'auth_security_log_')]
class SecurityLogController extends AbstractController
{
    public function __construct(private readonly SecurityLogService $securityLogService)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page    = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('perPage', 50);

        return $this->json($this->securityLogService->getPaginatedList($page, $perPage, 'auth'));
    }

    #[Route('/clear', name: 'clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        return $this->json(['deleted' => $this->securityLogService->clear()]);
    }
}
