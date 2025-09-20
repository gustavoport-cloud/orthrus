<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Security\ApiUser;
use PHPUnit\Framework\TestCase;

final class ApiUserTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $sub      = 'user:123';
        $org      = 'org:456';
        $scopes   = ['profile.read', 'data.write'];
        $isClient = false;

        $apiUser = new ApiUser($sub, $org, $scopes, $isClient);

        $this->assertSame($sub, $apiUser->getUserIdentifier());
        $this->assertSame($org, $apiUser->getOrg());
        $this->assertSame($scopes, $apiUser->getScopes());
        $this->assertSame($isClient, $apiUser->isClient());
    }

    public function testConstructorWithClientUser(): void
    {
        $sub      = 'client:789';
        $org      = 'org:456';
        $scopes   = ['api.read', 'api.write'];
        $isClient = true;

        $apiUser = new ApiUser($sub, $org, $scopes, $isClient);

        $this->assertSame($sub, $apiUser->getUserIdentifier());
        $this->assertSame($org, $apiUser->getOrg());
        $this->assertSame($scopes, $apiUser->getScopes());
        $this->assertTrue($apiUser->isClient());
    }

    public function testConstructorWithEmptyScopes(): void
    {
        $apiUser = new ApiUser('user:123', 'org:456', [], false);

        $this->assertSame([], $apiUser->getScopes());
        $this->assertFalse($apiUser->isClient());
    }

    public function testGetRolesReturnsApiRole(): void
    {
        $apiUser = new ApiUser('user:123', 'org:456', ['scope1'], false);

        $roles = $apiUser->getRoles();

        $this->assertSame(['ROLE_API'], $roles);
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $apiUser        = new ApiUser('user:123', 'org:456', ['scope1'], false);
        $originalScopes = $apiUser->getScopes();
        $originalOrg    = $apiUser->getOrg();
        $originalSub    = $apiUser->getUserIdentifier();

        $apiUser->eraseCredentials();

        // All properties should remain unchanged
        $this->assertSame($originalSub, $apiUser->getUserIdentifier());
        $this->assertSame($originalOrg, $apiUser->getOrg());
        $this->assertSame($originalScopes, $apiUser->getScopes());
    }

    public function testGetUserIdentifierIsConsistentWithSub(): void
    {
        $sub     = 'user:unique-id-123';
        $apiUser = new ApiUser($sub, 'org:456', [], false);

        $this->assertSame($sub, $apiUser->getUserIdentifier());
    }

    public function testScopesArrayIsImmutable(): void
    {
        $scopes  = ['profile.read', 'data.write'];
        $apiUser = new ApiUser('user:123', 'org:456', $scopes, false);

        $returnedScopes   = $apiUser->getScopes();
        $returnedScopes[] = 'additional.scope';

        // Original scopes should not be modified
        $this->assertSame(['profile.read', 'data.write'], $apiUser->getScopes());
        $this->assertNotSame($returnedScopes, $apiUser->getScopes());
    }

    public function testWithComplexScopeNames(): void
    {
        $complexScopes = [
            'user:profile:read',
            'admin:users:write',
            'api.v2.data.read',
            'billing:invoices:create',
        ];

        $apiUser = new ApiUser('user:123', 'org:456', $complexScopes, false);

        $this->assertSame($complexScopes, $apiUser->getScopes());
    }

    public function testWithUuidIdentifiers(): void
    {
        $sub = 'user:550e8400-e29b-41d4-a716-446655440000';
        $org = 'org:6ba7b810-9dad-11d1-80b4-00c04fd430c8';

        $apiUser = new ApiUser($sub, $org, [], false);

        $this->assertSame($sub, $apiUser->getUserIdentifier());
        $this->assertSame($org, $apiUser->getOrg());
    }

    public function testClientUserWithDifferentScopePattern(): void
    {
        $clientScopes = [
            'read:all',
            'write:users',
            'admin:system',
        ];

        $apiUser = new ApiUser('client:service-123', 'org:corp', $clientScopes, true);

        $this->assertTrue($apiUser->isClient());
        $this->assertSame($clientScopes, $apiUser->getScopes());
        $this->assertSame('client:service-123', $apiUser->getUserIdentifier());
    }

    public function testPropertiesAreReadOnly(): void
    {
        $apiUser = new ApiUser('user:123', 'org:456', ['scope1'], false);

        // These should be the same instances/values on repeated calls
        $this->assertSame($apiUser->getUserIdentifier(), $apiUser->getUserIdentifier());
        $this->assertSame($apiUser->getOrg(), $apiUser->getOrg());
        $this->assertSame($apiUser->getScopes(), $apiUser->getScopes());
        $this->assertSame($apiUser->isClient(), $apiUser->isClient());
        $this->assertSame($apiUser->getRoles(), $apiUser->getRoles());
    }
}
