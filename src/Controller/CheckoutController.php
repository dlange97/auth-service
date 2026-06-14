<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CheckoutInviteRepository;
use App\Service\CheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/checkout', name: 'auth_checkout_')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly CheckoutInviteRepository $checkoutInviteRepository,
    ) {
    }

    #[Route('/{hash}/validate', name: 'validate', methods: ['GET'])]
    public function validate(string $hash): JsonResponse
    {
        $invite = $this->checkoutInviteRepository->findUnusedByHash($hash);

        if ($invite === null) {
            return $this->json(['valid' => false, 'reason' => 'Invalid or already used invite link.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['valid' => true]);
    }

    #[Route('/{hash}', name: 'complete', methods: ['POST'])]
    public function complete(string $hash, Request $request): JsonResponse
    {
        try {
            $invite = $this->checkoutService->findValidInvite($hash);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $result = $this->checkoutService->completeCheckout($invite, $data);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (ConflictHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message'    => 'Instance created successfully.',
            'instanceId' => $result['instanceId'],
            'subdomain'  => $result['subdomain'],
            'token'      => $result['token'],
        ], Response::HTTP_CREATED);
    }
}
