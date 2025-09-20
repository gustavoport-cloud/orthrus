<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\ApiUser;
use App\Security\JwtAuthenticator;
use App\Security\TokenClaims;
use App\Service\TokenService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class JwtAuthenticatorTest extends TestCase
{
    private JwtAuthenticator $authenticator;
    private TokenService&MockObject $tokenService;

    protected function setUp(): void
    {
        $this->tokenService  = $this->createMock(TokenService::class);
        $this->authenticator = new JwtAuthenticator($this->tokenService);
    }

    public function testSupportsReturnsTrueForBearerToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer token123');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseForNonBearerToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForNoAuthorizationHeader(): void
    {
        $request = new Request();

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForEmptyAuthorizationHeader(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', '');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testAuthenticateSuccessWithValidToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer valid.jwt.token');
        $request->headers->set('X-Org-Id', 'org-123');

        $tokenClaims = new TokenClaims(
            'user:user-123',
            'org-123',
            ['profile.read'],
            'jti-123',
            time() + 3600,
            false
        );

        $this->tokenService->expects($this->once())
            ->method('verifyAndParse')
            ->with('valid.jwt.token')
            ->willReturn($tokenClaims);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $userBadge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
        $this->assertNotNull($userBadge);

        $user = $userBadge->getUserLoader()();
        $this->assertInstanceOf(ApiUser::class, $user);
        $this->assertSame('user:user-123', $user->getUserIdentifier());
        $this->assertSame('org-123', $user->getOrg());
        $this->assertSame(['profile.read'], $user->getScopes());
        $this->assertFalse($user->isClient());
    }

    public function testAuthenticateWithClientToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer client.jwt.token');
        $request->headers->set('X-Org-Id', 'org-456');

        $tokenClaims = new TokenClaims(
            'client:client-789',
            'org-456',
            ['api.read', 'api.write'],
            'jti-456',
            time() + 3600,
            true
        );

        $this->tokenService->expects($this->once())
            ->method('verifyAndParse')
            ->with('client.jwt.token')
            ->willReturn($tokenClaims);

        $passport  = $this->authenticator->authenticate($request);
        $userBadge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
        $user      = $userBadge->getUserLoader()();

        $this->assertInstanceOf(ApiUser::class, $user);
        $this->assertSame('client:client-789', $user->getUserIdentifier());
        $this->assertSame('org-456', $user->getOrg());
        $this->assertSame(['api.read', 'api.write'], $user->getScopes());
        $this->assertTrue($user->isClient());
    }

    public function testAuthenticateThrowsExceptionOnInvalidToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer invalid.token');
        $request->headers->set('X-Org-Id', 'org-123');

        $this->tokenService->expects($this->once())
            ->method('verifyAndParse')
            ->with('invalid.token')
            ->willThrowException(new \RuntimeException('Invalid token'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateDoesNotCheckOrgHeader(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer valid.jwt.token');
        // No X-Org-Id header here; org checks happen in OrgVoter at controller level

        $tokenClaims = new TokenClaims(
            'user:user-123',
            'org-123',
            ['profile.read'],
            'jti-123',
            time() + 3600,
            false
        );

        $this->tokenService->expects($this->once())
            ->method('verifyAndParse')
            ->willReturn($tokenClaims);

        $passport = $this->authenticator->authenticate($request);
        $this->assertNotNull($passport);
    }

    public function testAuthenticateHandlesWhitespaceInToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer   whitespace.jwt.token   ');
        $request->headers->set('X-Org-Id', 'org-123');

        $tokenClaims = new TokenClaims(
            'user:user-123',
            'org-123',
            ['profile.read'],
            'jti-123',
            time() + 3600,
            false
        );

        $this->tokenService->expects($this->once())
            ->method('verifyAndParse')
            ->with('whitespace.jwt.token')
            ->willReturn($tokenClaims);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token   = $this->createMock(TokenInterface::class);

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNull($result);
    }

    public function testOnAuthenticationFailureReturnsJsonResponse(): void
    {
        $request   = new Request();
        $exception = new AuthenticationException('Test error');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertSame('about:blank', $content['type']);
        $this->assertSame('Unauthorized', $content['title']);
        $this->assertSame(401, $content['status']);
        $this->assertSame('Invalid or expired token', $content['detail']);
    }

    public function testStartReturnsJsonResponse(): void
    {
        $request = new Request();

        $response = $this->authenticator->start($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertSame('about:blank', $content['type']);
        $this->assertSame('Unauthorized', $content['title']);
        $this->assertSame(401, $content['status']);
        $this->assertSame('Authentication required', $content['detail']);
    }

    public function testStartWithException(): void
    {
        $request   = new Request();
        $exception = new \Exception('Some auth exception');

        $response = $this->authenticator->start($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAuthenticateWithEmptyScopes(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer token.with.empty.scopes');
        $request->headers->set('X-Org-Id', 'org-123');

        $tokenClaims = new TokenClaims(
            'user:user-123',
            'org-123',
            [],
            'jti-123',
            time() + 3600,
            false
        );

        $this->tokenService->expects($this->once())
            ->method('verifyAndParse')
            ->willReturn($tokenClaims);

        $passport  = $this->authenticator->authenticate($request);
        $userBadge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
        $user      = $userBadge->getUserLoader()();

        $this->assertInstanceOf(ApiUser::class, $user);
        $this->assertSame([], $user->getScopes());
    }
}
