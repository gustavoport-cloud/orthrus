<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_refresh_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_refresh_by_user_org_revoked', columns: ['user_id', 'org_id', 'revoked_at'])]
#[ORM\Index(name: 'idx_refresh_by_client_org', columns: ['client_id', 'org_id'])]
#[ORM\Index(name: 'idx_refresh_jti', columns: ['jti'])]
#[ORM\Index(name: 'idx_refresh_expires', columns: ['expires_at'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, name: 'token_hash')]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: OAuthClient::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?OAuthClient $client = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $jti;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'org_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $org;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, name: 'user_agent')]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'expires_at')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\OneToOne(targetEntity: RefreshToken::class)]
    #[ORM\JoinColumn(name: 'replaced_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RefreshToken $replacedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'revoked_at')]
    private ?\DateTimeImmutable $revokedAt = null;

    // @phpstan-ignore-next-line property.onlyWritten
    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Organization $org, string $tokenHash, string $jti)
    {
        $this->id        = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->org       = $org;
        $this->tokenHash = $tokenHash;
        $this->jti       = $jti;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }
    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): void
    {
        $this->user = $user;
    }
    public function getClient(): ?OAuthClient
    {
        return $this->client;
    }
    public function setClient(?OAuthClient $client): void
    {
        $this->client = $client;
    }
    public function getJti(): string
    {
        return $this->jti;
    }
    public function getOrg(): Organization
    {
        return $this->org;
    }
    public function getIp(): ?string
    {
        return $this->ip;
    }
    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
    public function setUserAgent(?string $ua): void
    {
        $this->userAgent = $ua;
    }
    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
    public function setExpiresAt(\DateTimeImmutable $dt): void
    {
        $this->expiresAt = $dt;
    }
    public function getReplacedBy(): ?RefreshToken
    {
        return $this->replacedBy;
    }
    public function setReplacedBy(?RefreshToken $rt): void
    {
        $this->replacedBy = $rt;
    }
    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }
    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }
    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }
}
