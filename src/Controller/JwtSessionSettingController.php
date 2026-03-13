<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\JwtSessionSetting;
use App\Entity\User;
use App\Repository\JwtSessionSettingRepository;
use App\Security\PermissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/auth/settings/jwt-session', name: 'auth_jwt_session_')]
class JwtSessionSettingController extends AbstractController
{
    public function __construct(
        private readonly JwtSessionSettingRepository $settingRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if ($error = $this->denyUnlessAdmin()) {
            return $error;
        }

        $items = array_map($this->serialize(...), $this->settingRepository->findAllOrdered());

        return $this->json($items);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        if ($error = $this->denyUnlessAdmin()) {
            return $error;
        }

        $setting = $this->settingRepository->find($id);
        if (!$setting) {
            return $this->json(['error' => 'Setting not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($setting));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ($error = $this->denyUnlessAdmin()) {
            return $error;
        }

        $data = $this->decodeJson($request);

        $setting = $this->settingRepository->findLatest() ?? new JwtSessionSetting();
        $setting->setName($this->normalizeName($data['name'] ?? null));

        $ttl = $this->extractTtlSeconds($data);
        if ($ttl === null) {
            return $this->json(['error' => 'ttlSeconds or ttlDays is required.'], Response::HTTP_BAD_REQUEST);
        }
        $setting->setTtlSeconds($ttl);

        if ($validationError = $this->validateSetting($setting)) {
            return $validationError;
        }

        $status = $setting->getId() === null ? Response::HTTP_CREATED : Response::HTTP_OK;
        $this->settingRepository->save($setting, true);

        foreach ($this->settingRepository->findAllOrdered() as $item) {
            if ($item->getId() !== $setting->getId()) {
                $this->settingRepository->remove($item);
            }
        }
        $this->settingRepository->flush();

        return $this->json($this->serialize($setting), $status);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if ($error = $this->denyUnlessAdmin()) {
            return $error;
        }

        $setting = $this->settingRepository->find($id);
        if (!$setting) {
            return $this->json(['error' => 'Setting not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->decodeJson($request);

        if (array_key_exists('name', $data)) {
            $setting->setName($this->normalizeName($data['name']));
        }

        $ttl = $this->extractTtlSeconds($data);
        if ($ttl !== null) {
            $setting->setTtlSeconds($ttl);
        }

        if ($validationError = $this->validateSetting($setting)) {
            return $validationError;
        }

        $this->settingRepository->save($setting, true);

        return $this->json($this->serialize($setting));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        if ($error = $this->denyUnlessAdmin()) {
            return $error;
        }

        $setting = $this->settingRepository->find($id);
        if (!$setting) {
            return $this->json(['error' => 'Setting not found.'], Response::HTTP_NOT_FOUND);
        }

        if (count($this->settingRepository->findAllOrdered()) <= 1) {
            return $this->json(['error' => 'At least one JWT session setting must remain active.'], Response::HTTP_CONFLICT);
        }

        $this->settingRepository->remove($setting, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function denyUnlessAdmin(): ?JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser || !in_array(PermissionService::ROLE_ADMIN, $currentUser->getRoles(), true)) {
            return $this->json(['error' => 'Forbidden. Admin role required.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function decodeJson(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    private function normalizeName(mixed $name): string
    {
        if (!is_string($name) || trim($name) === '') {
            return 'Default JWT Session';
        }

        return trim($name);
    }

    private function extractTtlSeconds(array $data): ?int
    {
        if (array_key_exists('ttlSeconds', $data) && is_numeric($data['ttlSeconds'])) {
            return (int) $data['ttlSeconds'];
        }

        if (array_key_exists('ttlDays', $data) && is_numeric($data['ttlDays'])) {
            return (int) round(((float) $data['ttlDays']) * 86400);
        }

        return null;
    }

    private function validateSetting(JwtSessionSetting $setting): ?JsonResponse
    {
        $errors = $this->validator->validate($setting);
        if (count($errors) === 0) {
            return null;
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
        }

        return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array<string, mixed> */
    private function serialize(JwtSessionSetting $setting): array
    {
        return [
            'id' => $setting->getId(),
            'name' => $setting->getName(),
            'ttlSeconds' => $setting->getTtlSeconds(),
            'ttlDays' => round($setting->getTtlSeconds() / 86400, 2),
            'createdAt' => $setting->getCreatedAt()?->format('c'),
            'updatedAt' => $setting->getUpdatedAt()?->format('c'),
        ];
    }
}
