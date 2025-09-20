<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Table(name: 'memberships')]
#[ORM\Index(columns: ['user_id', 'org_id'], name: 'idx_membership_user_org')]
class Membership
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'org_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $org;

    #[ORM\Column(type: 'string', length: 64)]
    private string $role;

    public function __construct(User $user, Organization $org, string $role)
    {
        $this->id   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->user = $user;
        $this->org  = $org;
        $this->role = $role;
    }

    public function getId(): string
    {
        return $this->id;
    }
    public function getUser(): User
    {
        return $this->user;
    }
    public function getOrg(): Organization
    {
        return $this->org;
    }
    public function getRole(): string
    {
        return $this->role;
    }
    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
