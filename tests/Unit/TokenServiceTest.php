<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\RevokedJtiRepository;
use App\Service\TokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;
    private string $tempDir;
    private string $privateKeyPath;
    private string $publicKeyPath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/jwt_test_'.uniqid();
        @mkdir($this->tempDir, 0777, true);
        $this->privateKeyPath = $this->tempDir.'/private.pem';
        $this->publicKeyPath  = $this->tempDir.'/public.pem';

        exec("openssl genrsa -out {$this->privateKeyPath} 2048 >/dev/null 2>&1");
        exec("openssl rsa -in {$this->privateKeyPath} -pubout -out {$this->publicKeyPath} >/dev/null 2>&1");

        $params = new ParameterBag([
            'jwt' => [
                'issuer'     => 'test-issuer',
                'audience'   => 'test-audience',
                'access_ttl' => 900,
                'skew'       => 60,
                'keys'       => [
                    'current' => [
                        'kid'          => 'test-kid-123',
                        'private_path' => $this->privateKeyPath,
                        'public_path'  => $this->publicKeyPath,
                    ],
                    'previous' => [],
                ],
            ],
        ]);

        $revokedJtiRepository = $this->createMock(RevokedJtiRepository::class);
        $revokedJtiRepository->method('find')->willReturn(null); // No revoked tokens by default

        $this->tokenService = new TokenService($params, $revokedJtiRepository);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->privateKeyPath)) {
            unlink($this->privateKeyPath);
        }
        if (file_exists($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testCreateAccessTokenForUser(): void
    {
        $user   = new User('test@example.com');
        $org    = new Organization('Test Org');
        $scopes = ['profile.read', 'data.write'];

        $jwt = $this->tokenService->createAccessTokenForUser($user, $org, $scopes);

        $this->assertIsString($jwt);
        $this->assertStringContainsString('.', $jwt);

        $claims = $this->tokenService->verifyAndParse($jwt);

        $this->assertSame('user:'.$user->getId(), $claims->sub);
        $this->assertSame($org->getId(), $claims->org);
        $this->assertSame($scopes, $claims->scopes);
        $this->assertFalse($claims->isClient);
        $this->assertIsString($claims->jti);
        $this->assertGreaterThan(time(), $claims->exp);
    }

    public function testCreateAccessTokenForClient(): void
    {
        $client = new OAuthClient('Test Client', 'test-client-id');
        $org    = new Organization('Test Org');
        $scopes = ['api.read'];

        $jwt = $this->tokenService->createAccessTokenForClient($client, $org, $scopes);

        $this->assertIsString($jwt);
        $this->assertStringContainsString('.', $jwt);

        $claims = $this->tokenService->verifyAndParse($jwt);

        $this->assertSame('client:'.$client->getId(), $claims->sub);
        $this->assertSame($org->getId(), $claims->org);
        $this->assertSame($scopes, $claims->scopes);
        $this->assertTrue($claims->isClient);
        $this->assertIsString($claims->jti);
        $this->assertGreaterThan(time(), $claims->exp);
    }

    public function testCreateTokenWithEmptyScopes(): void
    {
        $user   = new User('test@example.com');
        $org    = new Organization('Test Org');
        $scopes = [];

        $jwt    = $this->tokenService->createAccessTokenForUser($user, $org, $scopes);
        $claims = $this->tokenService->verifyAndParse($jwt);

        $this->assertSame([], $claims->scopes);
    }

    public function testCreateTokenWithMultipleScopes(): void
    {
        $user   = new User('test@example.com');
        $org    = new Organization('Test Org');
        $scopes = ['profile.read', 'profile.write', 'data.read', 'data.write'];

        $jwt    = $this->tokenService->createAccessTokenForUser($user, $org, $scopes);
        $claims = $this->tokenService->verifyAndParse($jwt);

        $this->assertSame($scopes, $claims->scopes);
    }

    public function testVerifyAndParseThrowsOnInvalidToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->tokenService->verifyAndParse('invalid.jwt.token');
    }

    public function testVerifyAndParseThrowsOnExpiredToken(): void
    {
        $expiredParams = new ParameterBag([
            'jwt' => [
                'issuer'     => 'test-issuer',
                'audience'   => 'test-audience',
                'access_ttl' => -1,
                'skew'       => 0,
                'keys'       => [
                    'current' => [
                        'kid'          => 'test-kid-123',
                        'private_path' => $this->privateKeyPath,
                        'public_path'  => $this->publicKeyPath,
                    ],
                ],
            ],
        ]);

        $revokedJtiRepo = $this->createMock(RevokedJtiRepository::class);
        $revokedJtiRepo->method('find')->willReturn(null);

        $expiredTokenService = new TokenService($expiredParams, $revokedJtiRepo);
        $user                = new User('test@example.com');
        $org                 = new Organization('Test Org');

        $jwt = $expiredTokenService->createAccessTokenForUser($user, $org, []);

        sleep(1);

        $this->expectException(\RuntimeException::class);
        $expiredTokenService->verifyAndParse($jwt);
    }

    public function testGetTtl(): void
    {
        $ttl = $this->tokenService->getTtl();
        $this->assertSame(900, $ttl);
    }

    public function testTokenHasCorrectStructure(): void
    {
        $user = new User('test@example.com');
        $org  = new Organization('Test Org');

        $jwt   = $this->tokenService->createAccessTokenForUser($user, $org, ['profile.read']);
        $parts = explode('.', $jwt);

        $this->assertCount(3, $parts);

        $header = json_decode(base64_decode($parts[0]), true);
        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
        $this->assertSame('test-kid-123', $header['kid']);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertSame('test-issuer', $payload['iss']);
        $this->assertSame('test-audience', $payload['aud']);
        $this->assertSame('user:'.$user->getId(), $payload['sub']);
        $this->assertSame($org->getId(), $payload['org']);
        $this->assertSame('profile.read', $payload['scope']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('nbf', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
    }

    public function testVerifyAndParseThrowsOnRevokedToken(): void
    {
        $user = new User('test@example.com');
        $org  = new Organization('Test Org');
        $jwt  = $this->tokenService->createAccessTokenForUser($user, $org, ['profile.read']);

        // Create a new service instance with a mock that returns a revoked JTI
        $revokedJtiRepository = $this->createMock(RevokedJtiRepository::class);
        $revokedJtiRepository->method('find')->willReturn(true); // Token is revoked

        $params = new ParameterBag([
            'jwt' => [
                'issuer'     => 'test-issuer',
                'audience'   => 'test-audience',
                'access_ttl' => 900,
                'skew'       => 0,
                'keys'       => [
                    'current' => [
                        'kid'          => 'test-kid',
                        'private_path' => $this->privateKeyPath,
                        'public_path'  => $this->publicKeyPath,
                    ],
                ],
            ],
        ]);

        $tokenServiceWithRevokedJti = new TokenService($params, $revokedJtiRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token has been revoked');

        $tokenServiceWithRevokedJti->verifyAndParse($jwt);
    }
}
