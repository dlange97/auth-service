<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JwtSessionSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JwtSessionSettingRepository::class)]
#[ORM\Table(name: 'jwt_session_setting')]
#[ORM\HasLifecycleCallbacks]
class JwtSessionSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Assert\NotBlank(message: 'Setting name is required.')]
    #[Assert\Length(max: 80)]
    private string $name = 'Default JWT Session';

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(
        min: 300,
        max: 31536000,
        notInRangeMessage: 'JWT session must be between {{ min }} and {{ max }} seconds.'
    )]
    private int $ttlSeconds = 2592000;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getTtlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function setTtlSeconds(int $ttlSeconds): static
    {
        $this->ttlSeconds = $ttlSeconds;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}