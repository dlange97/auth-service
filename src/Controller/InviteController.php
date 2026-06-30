<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InviteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/invite', name: 'auth_invite_')]
class InviteController extends AbstractController
{
    public function __construct(
        private readonly InviteService $inviteService,
    ) {
    }

    #[Route('/{token}/validate', name: 'validate', methods: ['GET'])]
    public function validate(string $token): JsonResponse
    {
        $invite = $this->inviteService->findUsableInvite($token);

        if ($invite === null) {
            return $this->json(
                ['valid' => false, 'reason' => 'This invitation link is invalid or has already been used.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json([
            'valid' => true,
            'email' => $invite->getEmail(),
        ]);
    }

    #[Route('/{token}', name: 'accept', methods: ['POST'])]
    public function accept(string $token, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data     = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($data['password'] ?? '');

        try {
            $this->inviteService->acceptInvite($token, $password);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'Password set successfully. You can now sign in.',
        ], Response::HTTP_OK);
    }
}
