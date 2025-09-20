<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RevokedJtiRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RevokedJtiRepository::class)]
#[ORM\Table(name: 'revoked_jti')]
class RevokedJti
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $jti;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $jti, ?string $reason = null)
    {
        $this->jti       = $jti;
        $this->reason    = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getJti(): string
    {
        return $this->jti;
    }
    public function getReason(): ?string
    {
        return $this->reason;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
