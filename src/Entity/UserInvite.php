<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserInviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserInviteRepository::class)]
#[ORM\Table(name: 'user_invite')]
class UserInvite
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED  = 'expired';
    public const STATUS_REVOKED  = 'revoked';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Unique public identifier of the invitation (UUID), persisted in the DB.
     * Safe to expose/log; it is NOT the secret used to accept the invite.
     */
    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    private string $reference = '';

    /**
     * SHA-256 hash of the single-use secret token. The raw token is never stored.
     */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $tokenHash = '';

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $userId = '';

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email = '';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+72 hours');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsable(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    public function markAccepted(): static
    {
        $this->status     = self::STATUS_ACCEPTED;
        $this->acceptedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markExpired(): static
    {
        $this->status = self::STATUS_EXPIRED;
        return $this;
    }

    public function markRevoked(): static
    {
        $this->status = self::STATUS_REVOKED;
        return $this;
    }
}
