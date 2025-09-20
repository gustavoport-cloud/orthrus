<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;

final class FullApiFlowTest extends BaseApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Ensure KID is present for config resolution
        putenv('JWT_KID=test-kid');
        $_SERVER['JWT_KID'] = 'test-kid';
        $_ENV['JWT_KID'] = 'test-kid';
        self::setUpKeys();
        // Boot and reset schema
        $kernel = static::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        self::resetSchema($em);
        self::seedDemo($em);
        static::ensureKernelShutdown();
    }

    public function test_jwks_is_published(): void
    {
        $res = $this->requestJson('GET', '/.well-known/jwks.json');
        self::assertSame(200, $res['status']);
        $data = json_decode($res['body'], true);
        self::assertIsArray($data['keys'] ?? null);
        self::assertNotEmpty($data['keys']);
        self::assertArrayHasKey('kid', $data['keys'][0]);
    }

    public function test_me_requires_auth(): void
    {
        $res = $this->requestJson('GET', '/me');
        self::assertSame(401, $res['status']);
    }

    public function test_login_valid_user_get_tokens_and_access_me(): void
    {
        // Fetch seeded org id via /jwks test replaced by reading from DB
        $kernel = static::bootKernel();
        /** @var EntityManagerInterface $em */
        $em    = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $orgId = $em->getConnection()->executeQuery('SELECT id FROM organizations LIMIT 1')->fetchOne();
        static::ensureKernelShutdown();

        $login = $this->requestJson('POST', '/login', [], [
            'email'    => 'user@example.com',
            'password' => 'password',
            'org'      => $orgId,
            'scope'    => ['profile.read'],
        ]);
        self::assertSame(200, $login['status'], $login['body']);
        $payload = json_decode($login['body'], true);
        self::assertIsString($payload['access_token'] ?? null);
        self::assertIsString($payload['refresh_token'] ?? null);

        $me = $this->requestJson('GET', '/me', [
            'Authorization' => 'Bearer '.$payload['access_token'],
            'X-Org-Id'      => $orgId,
            'Accept'        => 'application/json',
        ]);
        self::assertSame(200, $me['status'], $me['body']);
        $meJson = json_decode($me['body'], true);
        self::assertSame($orgId, $meJson['org'] ?? null);
        self::assertFalse($meJson['client'] ?? true);
        self::assertContains('profile.read', $meJson['scopes'] ?? []);
    }

    public function test_refresh_rotates_and_old_reuse_is_rejected(): void
    {
        $kernel = static::bootKernel();
        /** @var EntityManagerInterface $em */
        $em    = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $orgId = $em->getConnection()->executeQuery('SELECT id FROM organizations LIMIT 1')->fetchOne();
        static::ensureKernelShutdown();

        $login = $this->requestJson('POST', '/login', [], [
            'email'    => 'user@example.com',
            'password' => 'password',
            'org'      => $orgId,
        ]);
        $payload = json_decode($login['body'], true);
        $rt1     = $payload['refresh_token'];

        $r1 = $this->requestJson('POST', '/token/refresh', [], [
            'refresh_token' => $rt1,
            'org'           => $orgId,
        ]);
        self::assertSame(200, $r1['status'], $r1['body']);
        $p1  = json_decode($r1['body'], true);
        $rt2 = $p1['refresh_token'];

        // Reuse old token should be rejected and chain revoked
        $reuse = $this->requestJson('POST', '/token/refresh', [], [
            'refresh_token' => $rt1,
            'org'           => $orgId,
        ]);
        self::assertSame(401, $reuse['status']);
    }

    public function test_client_credentials_issues_token_and_access_me_fails_without_scope(): void
    {
        $kernel = static::bootKernel();
        /** @var EntityManagerInterface $em */
        $em    = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $orgId = $em->getConnection()->executeQuery('SELECT id FROM organizations LIMIT 1')->fetchOne();
        static::ensureKernelShutdown();

        $basic = base64_encode('demo-client:secret');
        // Request with no scope
        $resp = $this->requestJson('POST', '/token', [
            'Authorization' => 'Basic '.$basic,
        ], [
            'org'   => $orgId,
            'scope' => [],
        ]);
        self::assertSame(200, $resp['status']);
        $body   = json_decode($resp['body'], true);
        $access = $body['access_token'];

        // Accessing /me without scope should be forbidden by ScopeVoter
        $me = $this->requestJson('GET', '/me', [
            'Authorization' => 'Bearer '.$access,
            'X-Org-Id'      => $orgId,
            'Accept'        => 'application/json',
        ]);
        self::assertSame(403, $me['status']);
    }

    public function test_org_mismatch_returns_403(): void
    {
        $kernel = static::bootKernel();
        /** @var EntityManagerInterface $em */
        $em    = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $orgId = $em->getConnection()->executeQuery('SELECT id FROM organizations LIMIT 1')->fetchOne();
        static::ensureKernelShutdown();

        $login = $this->requestJson('POST', '/login', [], [
            'email'    => 'user@example.com',
            'password' => 'password',
            'org'      => $orgId,
        ]);
        $payload = json_decode($login['body'], true);
        $access  = $payload['access_token'];

        $wrong = '00000000-0000-4000-8000-000000000000';
        $me    = $this->requestJson('GET', '/me', [
            'Authorization' => 'Bearer '.$access,
            'X-Org-Id'      => $wrong,
            'Accept'        => 'application/json',
        ]);
        self::assertSame(403, $me['status']);
    }
}
