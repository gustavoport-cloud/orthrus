<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\TokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function supports(Request $request): ?bool
    {
        $auth = $request->headers->get('Authorization');
        return $auth && str_starts_with($auth, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $auth = (string)$request->headers->get('Authorization');
        $jwt  = trim(substr($auth, 7));
        try {
            $claims = $this->tokens->verifyAndParse($jwt);
        } catch (\Throwable $e) {
            throw new \Symfony\Component\Security\Core\Exception\AuthenticationException('Invalid token');
        }
        $user = new ApiUser($claims->sub, $claims->org, $claims->scopes, $claims->isClient);
        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'type'   => 'about:blank',
            'title'  => 'Unauthorized',
            'status' => 401,
            'detail' => 'Invalid or expired token',
        ], 401);
    }

    public function start(Request $request, ?\Throwable $authException = null): ?Response
    {
        return new JsonResponse([
            'type'   => 'about:blank',
            'title'  => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication required',
        ], 401);
    }
}
