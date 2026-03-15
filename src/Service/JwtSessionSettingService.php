<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JwtSessionSetting;
use App\Repository\JwtSessionSettingRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class JwtSessionSettingService
{
    public function __construct(
        private readonly JwtSessionSettingRepository $settingRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return array_map($this->serialize(...), $this->settingRepository->findAllOrdered());
    }

    /** @return array<string, mixed> */
    public function findOrFail(int $id): array
    {
        $setting = $this->settingRepository->find($id);
        if (!$setting) {
            throw new NotFoundHttpException('Setting not found.');
        }

        return $this->serialize($setting);
    }

    /**
     * @param  array<string, mixed>                            $data
     * @return array{data: array<string, mixed>, isNew: bool}
     */
    public function createOrReplace(array $data): array
    {
        $ttl = $this->extractTtlSeconds($data);
        if ($ttl === null) {
            throw new BadRequestHttpException('ttlSeconds or ttlDays is required.');
        }

        $setting = $this->settingRepository->findLatest() ?? new JwtSessionSetting();
        $isNew   = $setting->getId() === null;

        $setting->setName($this->normalizeName($data['name'] ?? null));
        $setting->setTtlSeconds($ttl);

        $this->validateOrFail($setting);

        $this->settingRepository->save($setting, true);

        foreach ($this->settingRepository->findAllOrdered() as $item) {
            if ($item->getId() !== $setting->getId()) {
                $this->settingRepository->remove($item);
            }
        }
        $this->settingRepository->flush();

        return ['data' => $this->serialize($setting), 'isNew' => $isNew];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $setting = $this->settingRepository->find($id);
        if (!$setting) {
            throw new NotFoundHttpException('Setting not found.');
        }

        if (array_key_exists('name', $data)) {
            $setting->setName($this->normalizeName($data['name']));
        }

        $ttl = $this->extractTtlSeconds($data);
        if ($ttl !== null) {
            $setting->setTtlSeconds($ttl);
        }

        $this->validateOrFail($setting);

        $this->settingRepository->save($setting, true);

        return $this->serialize($setting);
    }

    public function delete(int $id): void
    {
        $setting = $this->settingRepository->find($id);
        if (!$setting) {
            throw new NotFoundHttpException('Setting not found.');
        }

        if (count($this->settingRepository->findAllOrdered()) <= 1) {
            throw new ConflictHttpException('At least one JWT session setting must remain active.');
        }

        $this->settingRepository->remove($setting, true);
    }

    private function normalizeName(mixed $name): string
    {
        if (!is_string($name) || trim($name) === '') {
            return 'Default JWT Session';
        }

        return trim($name);
    }

    /** @param array<string, mixed> $data */
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

    private function validateOrFail(JwtSessionSetting $setting): void
    {
        $errors = $this->validator->validate($setting);
        if (count($errors) === 0) {
            return;
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
        }

        throw new UnprocessableEntityHttpException(implode('; ', $messages));
    }

    /** @return array<string, mixed> */
    private function serialize(JwtSessionSetting $setting): array
    {
        return [
            'id'         => $setting->getId(),
            'name'       => $setting->getName(),
            'ttlSeconds' => $setting->getTtlSeconds(),
            'ttlDays'    => round($setting->getTtlSeconds() / 86400, 2),
            'createdAt'  => $setting->getCreatedAt()?->format('c'),
            'updatedAt'  => $setting->getUpdatedAt()?->format('c'),
        ];
    }
}
