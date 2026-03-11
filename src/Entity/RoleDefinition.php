<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoleDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores both system roles (seeded, not deletable) and custom roles
 * created by admins.
 */
#[ORM\Entity(repositoryClass: RoleDefinitionRepository::class)]
#[ORM\Table(name: 'role_definition')]
class RoleDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** Human-readable display name (e.g. "Editor", "Custom Viewer") */
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Role name is required.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /** Internal role slug used in User.roles (e.g. ROLE_EDITOR, ROLE_CUSTOM_1) */
    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Role slug is required.')]
    #[Assert\Regex(pattern: '/^ROLE_[A-Z0-9_]+$/', message: 'Slug must start with ROLE_ and contain only uppercase letters, digits and underscores.')]
    private string $slug = '';

    /** Ordered list of permission strings granted by this role */
    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    /** System roles are seeded and cannot be deleted */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isSystem = false;

    // ── Getters / Setters ──────────────────────────────────────────────────

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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = strtoupper(trim($slug));
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = array_values(array_unique($permissions));
        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;
        return $this;
    }
}
