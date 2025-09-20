<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\RevokedJtiRepository;
use App\Security\TokenClaims;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\RegisteredClaims as RC;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TokenService
{
    private Configuration $config;
    private string $issuer;
    private string $audience;
    private int $ttl;
    private int $skew;
    private string $kid;
    private string $publicPath;
    private ?RevokedJtiRepository $revokedJtiRepository;

    public function __construct(ParameterBagInterface $params, ?RevokedJtiRepository $revokedJtiRepository = null)
    {
        $this->revokedJtiRepository = $revokedJtiRepository;
        $jwt              = $params->get('jwt');
        $this->issuer     = $jwt['issuer'];
        $this->audience   = $jwt['audience'];
        $this->ttl        = (int)$jwt['access_ttl'];
        $this->skew       = (int)$jwt['skew'];
        $this->kid        = $jwt['keys']['current']['kid'];
        $this->publicPath = $jwt['keys']['current']['public_path'];

        $private      = (string)file_get_contents($jwt['keys']['current']['private_path']);
        $public       = (string)file_get_contents($this->publicPath);
        $this->config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($private),
            InMemory::plainText($public)
        );
        $this->config->setValidationConstraints();
    }

    /** @param list<string> $scopes */
    public function createAccessTokenForUser(User $user, Organization $org, array $scopes): string
    {
        $now   = new \DateTimeImmutable();
        $exp   = $now->modify("+{$this->ttl} seconds");
        $jti   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $token = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->relatedTo('user:'.$user->getId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($exp)
            ->identifiedBy($jti)
            ->withHeader('kid', $this->kid)
            ->withClaim('org', $org->getId())
            ->withClaim('scope', implode(' ', $scopes))
            ->getToken(new Sha256(), $this->config->signingKey());
        return $token->toString();
    }

    /** @param list<string> $scopes */
    public function createAccessTokenForClient(OAuthClient $client, Organization $org, array $scopes): string
    {
        $now   = new \DateTimeImmutable();
        $exp   = $now->modify("+{$this->ttl} seconds");
        $jti   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $token = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->relatedTo('client:'.$client->getId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($exp)
            ->identifiedBy($jti)
            ->withHeader('kid', $this->kid)
            ->withClaim('org', $org->getId())
            ->withClaim('scope', implode(' ', $scopes))
            ->getToken(new Sha256(), $this->config->signingKey());
        return $token->toString();
    }

    public function verifyAndParse(string $jwt): TokenClaims
    {
        $token = $this->config->parser()->parse($jwt);
        if (!$token instanceof UnencryptedToken) {
            throw new \RuntimeException('Invalid token');
        }
        $kid         = $token->headers()->get('kid');
        $pubPath     = $this->publicPath; // for rotation, a map can be added
        $public      = (string)file_get_contents($pubPath);
        $clock       = new \Symfony\Component\Clock\NativeClock();
        $constraints = [
            new SignedWith(new Sha256(), InMemory::plainText($public)),
            new IssuedBy($this->issuer),
            new PermittedFor($this->audience),
            new LooseValidAt($clock, new \DateInterval('PT'.max(0, (int)$this->skew).'S')),
        ];
        if (!$this->config->validator()->validate($token, ...$constraints)) {
            throw new \RuntimeException('Token verification failed');
        }
        $claims = $token->claims();
        $exp    = $claims->get(RC::EXPIRATION_TIME);
        $sub    = (string)$claims->get(RC::SUBJECT);
        $org    = (string)$claims->get('org');
        $scope  = (string)($claims->get('scope') ?? '');
        $scopes = array_values(array_filter(explode(' ', $scope)));
        $jti    = (string)$claims->get(RC::ID);

        // Check if JTI has been revoked (blacklisted) if repository provided
        if ($this->revokedJtiRepository && $this->revokedJtiRepository->find($jti)) {
            throw new \RuntimeException('Token has been revoked');
        }

        $isClient = str_starts_with($sub, 'client:');
        return new TokenClaims($sub, $org, $scopes, $jti, $exp->getTimestamp(), $isClient);
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

}
