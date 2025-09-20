<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RefreshTokenServiceTest extends TestCase
{
    private RefreshTokenService $service;
    private EntityManagerInterface&MockObject $em;
    private RefreshTokenRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->repo    = $this->createMock(RefreshTokenRepository::class);
        $this->service = new RefreshTokenService($this->em, $this->repo);
    }

    public function testIssueForUser(): void
    {
        $user = new User('test@example.com');
        $org  = new Organization('Test Org');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->issue($user, $org, '192.168.1.1', 'TestAgent/1.0');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('refresh', $result);
        $this->assertArrayHasKey('plain', $result);
        $this->assertInstanceOf(RefreshToken::class, $result['refresh']);
        $this->assertIsString($result['plain']);
        $this->assertStringContainsString('.', $result['plain']);

        $refreshToken = $result['refresh'];
        $this->assertSame($user, $refreshToken->getUser());
        $this->assertNull($refreshToken->getClient());
        $this->assertSame($org, $refreshToken->getOrg());
        $this->assertSame('192.168.1.1', $refreshToken->getIp());
        $this->assertSame('TestAgent/1.0', $refreshToken->getUserAgent());
        $this->assertFalse($refreshToken->isRevoked());
        $this->assertInstanceOf(\DateTimeImmutable::class, $refreshToken->getExpiresAt());
        $this->assertGreaterThan(new \DateTimeImmutable(), $refreshToken->getExpiresAt());
    }

    public function testIssueForClient(): void
    {
        $client = new OAuthClient('Test Client', 'test-client');
        $org    = new Organization('Test Org');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->issue($client, $org, null, null);

        $refreshToken = $result['refresh'];
        $this->assertNull($refreshToken->getUser());
        $this->assertSame($client, $refreshToken->getClient());
        $this->assertSame($org, $refreshToken->getOrg());
        $this->assertNull($refreshToken->getIp());
        $this->assertNull($refreshToken->getUserAgent());
    }

    public function testRotateSuccess(): void
    {
        $user            = new User('test@example.com');
        $org             = new Organization('Test Org');
        $oldRefreshToken = new RefreshToken($org, password_hash('secret123', PASSWORD_ARGON2ID), 'jti-123');
        $oldRefreshToken->setUser($user);
        $oldRefreshToken->setExpiresAt(new \DateTimeImmutable('+1 day'));

        $plainToken = $oldRefreshToken->getId() . '.secret123';

        $this->repo->expects($this->once())
            ->method('find')
            ->with($oldRefreshToken->getId())
            ->willReturn($oldRefreshToken);

        $this->em->expects($this->exactly(2))->method('flush');
        $this->em->expects($this->once())->method('persist');

        $result = $this->service->rotate($plainToken, $org->getId());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('refresh', $result);
        $this->assertArrayHasKey('plain', $result);
        $this->assertInstanceOf(RefreshToken::class, $result['refresh']);
        $this->assertTrue($oldRefreshToken->isRevoked());
        $this->assertSame($result['refresh'], $oldRefreshToken->getReplacedBy());
    }

    public function testRotateThrowsOnInvalidToken(): void
    {
        $this->repo->expects($this->once())
            ->method('find')
            ->with('invalid-id')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_refresh');

        $this->service->rotate('invalid-id.secret', 'org-id');
    }

    public function testRotateThrowsOnWrongOrg(): void
    {
        $org1         = new Organization('Org 1');
        $org2         = new Organization('Org 2');
        $refreshToken = new RefreshToken($org1, password_hash('secret', PASSWORD_ARGON2ID), 'jti-123');

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($refreshToken);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_org');

        $this->service->rotate($refreshToken->getId() . '.secret', $org2->getId());
    }

    public function testRotateThrowsOnExpiredToken(): void
    {
        $org          = new Organization('Test Org');
        $expiredToken = new RefreshToken($org, password_hash('secret', PASSWORD_ARGON2ID), 'jti-123');
        $expiredToken->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($expiredToken);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expired_or_revoked');

        $this->service->rotate($expiredToken->getId() . '.secret', $org->getId());
    }

    public function testRotateThrowsOnRevokedToken(): void
    {
        $org          = new Organization('Test Org');
        $revokedToken = new RefreshToken($org, password_hash('secret', PASSWORD_ARGON2ID), 'jti-123');
        $revokedToken->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $revokedToken->revoke();

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($revokedToken);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expired_or_revoked');

        $this->service->rotate($revokedToken->getId() . '.secret', $org->getId());
    }

    public function testRotateDetectsReuse(): void
    {
        $org      = new Organization('Test Org');
        $user     = new User('test@example.com');
        $oldToken = new RefreshToken($org, password_hash('secret', PASSWORD_ARGON2ID), 'jti-123');
        $oldToken->setUser($user);
        $oldToken->setExpiresAt(new \DateTimeImmutable('+1 day'));

        $newToken = new RefreshToken($org, password_hash('newsecret', PASSWORD_ARGON2ID), 'jti-456');
        $oldToken->setReplacedBy($newToken);

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($oldToken);

        $this->em->expects($this->once())->method('flush');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('reuse_detected');

        $this->service->rotate($oldToken->getId() . '.secret', $org->getId());
    }

    public function testRotateThrowsOnInvalidSecret(): void
    {
        $org          = new Organization('Test Org');
        $refreshToken = new RefreshToken($org, password_hash('correct-secret', PASSWORD_ARGON2ID), 'jti-123');
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($refreshToken);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_refresh');

        $this->service->rotate($refreshToken->getId() . '.wrong-secret', $org->getId());
    }

    public function testRevokeSuccess(): void
    {
        $org          = new Organization('Test Org');
        $refreshToken = new RefreshToken($org, password_hash('secret', PASSWORD_ARGON2ID), 'jti-123');

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($refreshToken);

        $this->em->expects($this->once())->method('flush');

        $this->service->revoke($refreshToken->getId() . '.secret');

        $this->assertTrue($refreshToken->isRevoked());
    }

    public function testRevokeWithInvalidToken(): void
    {
        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->em->expects($this->never())->method('flush');

        $this->service->revoke('invalid-id.secret');
    }

    public function testRevokeWithWrongSecret(): void
    {
        $org          = new Organization('Test Org');
        $refreshToken = new RefreshToken($org, password_hash('correct-secret', PASSWORD_ARGON2ID), 'jti-123');

        $this->repo->expects($this->once())
            ->method('find')
            ->willReturn($refreshToken);

        $this->em->expects($this->never())->method('flush');

        $this->service->revoke($refreshToken->getId() . '.wrong-secret');

        $this->assertFalse($refreshToken->isRevoked());
    }

    public function testDetectReuseAndRevokeChain(): void
    {
        $org    = new Organization('Test Org');
        $token1 = new RefreshToken($org, 'hash1', 'jti-1');
        $token2 = new RefreshToken($org, 'hash2', 'jti-2');
        $token3 = new RefreshToken($org, 'hash3', 'jti-3');

        $token1->setReplacedBy($token2);
        $token2->setReplacedBy($token3);

        $this->em->expects($this->once())->method('flush');

        $this->service->detectReuseAndRevokeChain($token1);

        $this->assertTrue($token1->isRevoked());
        $this->assertTrue($token2->isRevoked());
        $this->assertTrue($token3->isRevoked());
    }

    public function testSplitMalformedToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('malformed_refresh');

        $this->service->revoke('malformed-token-without-dot');
    }

    public function testGeneratedTokenFormat(): void
    {
        $user = new User('test@example.com');
        $org  = new Organization('Test Org');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result     = $this->service->issue($user, $org, null, null);
        $plainToken = $result['plain'];

        $parts = explode('.', $plainToken);
        $this->assertCount(2, $parts);
        $this->assertNotEmpty($parts[0]); // ID
        $this->assertNotEmpty($parts[1]); // Secret
        $this->assertIsString($parts[0]);
        $this->assertIsString($parts[1]);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]{36}$/', $parts[0]); // UUID format
    }
}
