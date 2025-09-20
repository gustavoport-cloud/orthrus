<?php

declare(strict_types=1);

namespace App\Security;

final class TokenClaims
{
    public function __construct(
        public readonly string $sub,
        public readonly string $org,
        /** @var string[] */
        public readonly array $scopes,
        public readonly string $jti,
        public readonly int $exp,
        public readonly bool $isClient,
    ) {
    }
}
