<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    private int $ttlSeconds;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $repo,
        int $refreshTokenTtlSeconds = 30 * 24 * 60 * 60 // 30 days default
    ) {
        $this->ttlSeconds = $refreshTokenTtlSeconds;
    }

    /**
     * @return array{refresh: RefreshToken, plain: string}
     */
    public function issue(User|OAuthClient $subject, Organization $org, ?string $ip, ?string $ua): array
    {
        $secret = self::generateSecret();
        $hash   = password_hash($secret, PASSWORD_ARGON2ID);
        $jti    = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $rt     = new RefreshToken($org, $hash, $jti);
        if ($subject instanceof User) {
            $rt->setUser($subject);
        } else {
            $rt->setClient($subject);
        }
        $rt->setIp($ip);
        $rt->setUserAgent($ua);
        $rt->setExpiresAt(new \DateTimeImmutable('+'.$this->ttlSeconds.' seconds'));
        $this->em->persist($rt);
        $this->em->flush();
        $plain = $rt->getId().'.'.$secret;
        return ['refresh' => $rt, 'plain' => $plain];
    }

    /**
     * @return array{refresh: RefreshToken, plain: string}
     */
    public function rotate(string $plain, string $orgId): array
    {
        [$id, $secret] = $this->split($plain);
        $rt            = $this->repo->find($id);
        if (!$rt) {
            throw new \RuntimeException('invalid_refresh');
        }
        if ($rt->getOrg()->getId() !== $orgId) {
            throw new \RuntimeException('invalid_org');
        }
        if ($rt->isRevoked() || $rt->getExpiresAt() < new \DateTimeImmutable()) {
            throw new \RuntimeException('expired_or_revoked');
        }
        // Reuse detection: if already replaced, chain was rotated previously
        if ($rt->getReplacedBy() !== null) {
            $this->revokeChain($rt);
            throw new \RuntimeException('reuse_detected');
        }
        if (!password_verify($secret, $rt->getTokenHash())) {
            throw new \RuntimeException('invalid_refresh');
        }
        // Rotate
        $subject = $rt->getUser() ?: $rt->getClient();
        $issued  = $this->issue($subject, $rt->getOrg(), $rt->getIp(), $rt->getUserAgent());
        $rt->setReplacedBy($issued['refresh']);
        $rt->revoke();
        $this->em->flush();
        return $issued;
    }

    public function revoke(string $plain): void
    {
        [$id, $secret] = $this->split($plain);
        $rt            = $this->repo->find($id);
        if (!$rt) {
            return;
        }
        if (password_verify($secret, $rt->getTokenHash())) {
            $rt->revoke();
            $this->em->flush();
        }
    }

    public function detectReuseAndRevokeChain(RefreshToken $rt): void
    {
        $this->revokeChain($rt);
    }

    private function revokeChain(RefreshToken $start): void
    {
        $node = $start;
        while ($node) {
            if (!$node->isRevoked()) {
                $node->revoke();
            }
            $node = $node->getReplacedBy();
        }
        $this->em->flush();
    }

    /** @return array{0:string,1:string} */
    private static function split(string $plain): array
    {
        $parts = explode('.', $plain, 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException('malformed_refresh');
        }
        return $parts;
    }

    private static function generateSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
