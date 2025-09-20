<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\ApiUser;
use App\Security\ScopeVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ScopeVoterTest extends TestCase
{
    private ScopeVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ScopeVoter();
    }

    // supports() is protected; coverage is inferred via vote() behavior below

    public function testVoteGrantsAccessWhenUserHasRequiredScope(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            ['profile.read', 'data.write'],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        $result = $this->voter->vote($token, null, ['scope:profile.read']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteDeniesAccessWhenUserDoesNotHaveRequiredScope(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            ['profile.read'],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        $result = $this->voter->vote($token, null, ['scope:data.write']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteDeniesAccessWhenUserIsNotApiUser(): void
    {
        $regularUser = $this->createMock(UserInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($regularUser);

        $result = $this->voter->vote($token, null, ['scope:profile.read']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteDeniesAccessWhenUserIsNull(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $result = $this->voter->vote($token, null, ['scope:profile.read']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteAbstainsWhenAttributeIsNotSupported(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            ['profile.read'],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->never())
            ->method('getUser');

        $result = $this->voter->vote($token, null, ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testVoteHandlesEmptyScope(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            [''],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        $result = $this->voter->vote($token, null, ['scope:']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteHandlesEmptyScopeRequirement(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            [],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        $result = $this->voter->vote($token, null, ['scope:']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteWithUserHavingEmptyScopes(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            [],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        $result = $this->voter->vote($token, null, ['scope:profile.read']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteWithClientUser(): void
    {
        $clientUser = new ApiUser(
            'client:789',
            'org:456',
            ['api.read', 'api.write'],
            true
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($clientUser);

        $result = $this->voter->vote($token, null, ['scope:api.read']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteWithMultipleAttributes(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            ['profile.read'],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        // When multiple attributes are provided, vote should only consider the first one
        $result = $this->voter->vote($token, null, ['scope:profile.read', 'scope:data.write']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteWithCaseSensitiveScopes(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            ['Profile.Read'],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($apiUser);

        // Scope matching should be case-sensitive
        $result = $this->voter->vote($token, null, ['scope:profile.read']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteWithComplexScopeNames(): void
    {
        $apiUser = new ApiUser(
            'user:123',
            'org:456',
            ['user:profile:read', 'admin:users:write', 'api.v2.data.read'],
            false
        );

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->exactly(3))
            ->method('getUser')
            ->willReturn($apiUser);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, ['scope:user:profile:read']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, ['scope:admin:users:write']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, ['scope:api.v2.data.read']));
    }
}
