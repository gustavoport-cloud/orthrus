<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class ApiUser implements UserInterface
{
    /** @param string[] $scopes */
    public function __construct(
        private readonly string $sub,
        private readonly string $org,
        private readonly array $scopes,
        private readonly bool $isClient,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->sub;
    }
    public function getRoles(): array
    {
        return ['ROLE_API'];
    }
    public function eraseCredentials(): void
    {
    }

    public function getOrg(): string
    {
        return $this->org;
    }
    /** @return string[] */
    public function getScopes(): array
    {
        return $this->scopes;
    }
    public function isClient(): bool
    {
        return $this->isClient;
    }
}
