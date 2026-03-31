<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InstanceRepository;
use App\Traits\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InstanceRepository::class)]
#[ORM\Table(name: 'instance')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['subdomain'], message: 'This subdomain is already taken.')]
class Instance
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /** URL-safe slug used as subdomain, e.g. "acme" → acme.mydashboard.local */
    #[ORM\Column(type: Types::STRING, length: 63, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 63)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/',
        message: 'Subdomain must contain only lowercase letters, digits and hyphens.'
    )]
    private string $subdomain = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $uuid): static
    {
        $this->id = $uuid;
        return $this;
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

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain): static
    {
        $this->subdomain = strtolower(trim($subdomain));
        return $this;
    }
}
