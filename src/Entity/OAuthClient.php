<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OAuthClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OAuthClientRepository::class)]
#[ORM\Table(name: 'oauth_clients')]
#[ORM\UniqueConstraint(name: 'uniq_client_id', columns: ['client_id'])]
class OAuthClient
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 64, name: 'client_id')]
    private string $clientId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $secretHash;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $allowedScopes = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $allowedOrgs = null; // null means all orgs

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    // @phpstan-ignore-next-line property.onlyWritten
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    // @phpstan-ignore-next-line property.onlyWritten
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rotatedAt = null;

    // @phpstan-ignore-next-line property.onlyWritten
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $clientId)
    {
        $this->id        = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->name      = $name;
        $this->clientId  = $clientId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    public function getClientId(): string
    {
        return $this->clientId;
    }
    public function setClientId(string $id): void
    {
        $this->clientId = $id;
    }
    public function getSecretHash(): string
    {
        return $this->secretHash;
    }
    public function setSecretHash(string $hash): void
    {
        $this->secretHash = $hash;
    }
    /** @return list<string>|null */
    public function getAllowedScopes(): ?array
    {
        return $this->allowedScopes;
    }
    /** @param list<string>|null $scopes */
    public function setAllowedScopes(?array $scopes): void
    {
        $this->allowedScopes = $scopes;
    }
    /** @return list<string>|null */
    public function getAllowedOrgs(): ?array
    {
        return $this->allowedOrgs;
    }
    /** @param list<string>|null $orgs */
    public function setAllowedOrgs(?array $orgs): void
    {
        $this->allowedOrgs = $orgs;
    }
    public function isActive(): bool
    {
        return $this->isActive;
    }
    public function setIsActive(bool $active): void
    {
        $this->isActive = $active;
    }
    public function touchLastUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }
}
