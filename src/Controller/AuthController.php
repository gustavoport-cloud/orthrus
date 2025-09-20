<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Repository\MembershipRepository;
use App\Repository\OAuthClientRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\RefreshTokenService;
use App\Service\TokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly OrganizationRepository $orgs,
        private readonly MembershipRepository $memberships,
        private readonly OAuthClientRepository $clients,
        private readonly TokenService $tokens,
        private readonly RefreshTokenService $refreshTokens,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
        #[Autowire(service: 'limiter.login')] private readonly RateLimiterFactory $loginLimiter,
        #[Autowire(service: 'limiter.client_token')] private readonly RateLimiterFactory $clientLimiter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route(path: '/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $limit = $this->loginLimiter->create($request->getClientIp() ?? 'anon');
        if (!$limit->consume(1)->isAccepted()) {
            return $this->problem(429, 'Too Many Requests', 'Rate limit exceeded');
        }
        $data       = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($data, new Assert\Collection(
            fields: [
                'email'    => new Assert\Required([new Assert\NotBlank(), new Assert\Email()]),
                'password' => new Assert\Required([new Assert\NotBlank()]),
                'org'      => new Assert\Required([new Assert\NotBlank(), new Assert\Uuid()]),
                'scope'    => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\Count(max: 10),
                    new Assert\All([new Assert\Regex('/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)*$/')])
                ]),
            ],
            allowExtraFields: true,
            allowMissingFields: false,
        ));
        if (count($violations)) {
            return $this->problem(400, 'Bad Request', 'Invalid request body');
        }
        $email    = (string)$data['email'];
        $password = (string)$data['password'];
        $orgId    = (string)$data['org'];
        $scopes   = array_values(array_unique(array_map('strval', $data['scope'] ?? [])));

        $user       = $this->users->findActiveByEmail($email);
        $userHasher = $this->hasherFactory->getPasswordHasher(\App\Entity\User::class);
        if (!$user || !$userHasher->verify($user->getPasswordHash(), $password)) {
            return $this->problem(401, 'Unauthorized', 'Invalid credentials');
        }
        $org = $this->orgs->find($orgId);
        if (!$org instanceof Organization || !$this->memberships->isMember($user, $org)) {
            return $this->problem(401, 'Unauthorized', 'User not in organization');
        }
        $access = $this->tokens->createAccessTokenForUser($user, $org, $scopes);
        $issued = $this->refreshTokens->issue($user, $org, $request->getClientIp(), $request->headers->get('User-Agent'));
        return $this->json([
            'access_token'  => $access,
            'expires_in'    => $this->tokens->getTtl(),
            'refresh_token' => $issued['plain'],
            'token_type'    => 'Bearer',
        ]);
    }

    #[Route(path: '/token/refresh', name: 'token_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $data       = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($data, new Assert\Collection(
            fields: [
                'refresh_token' => new Assert\Required([new Assert\NotBlank(), new Assert\Regex('/^[0-9a-f-]{36}\..+$/')]),
                'org'           => new Assert\Required([new Assert\NotBlank(), new Assert\Uuid()]),
            ],
            allowExtraFields: true,
        ));
        if (count($violations)) {
            return $this->problem(400, 'Bad Request', 'Invalid request body');
        }
        try {
            $rotated = $this->refreshTokens->rotate((string)$data['refresh_token'], (string)$data['org']);
        } catch (\Throwable $e) {
            return $this->problem(401, 'Unauthorized', 'Invalid refresh token');
        }
        $subject = $rotated['refresh']->getUser() ?: $rotated['refresh']->getClient();
        $org     = $rotated['refresh']->getOrg();
        $scopes  = []; // no scope tracking on refresh for simplicity
        if ($subject instanceof \App\Entity\User) {
            $access = $this->tokens->createAccessTokenForUser($subject, $org, $scopes);
        } else {
            $access = $this->tokens->createAccessTokenForClient($subject, $org, $scopes);
        }
        return $this->json([
            'access_token'  => $access,
            'expires_in'    => $this->tokens->getTtl(),
            'refresh_token' => $rotated['plain'],
            'token_type'    => 'Bearer',
        ]);
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $data  = json_decode($request->getContent(), true) ?? [];
        $token = (string)($data['refresh_token'] ?? '');
        if ($token !== '') {
            $this->refreshTokens->revoke($token);
        }
        return new Response('', 204);
    }

    #[Route(path: '/token', name: 'token_client', methods: ['POST'])]
    public function tokenClientCredentials(Request $request): Response
    {
        $limit = $this->clientLimiter->create($request->getClientIp() ?? 'anon');
        if (!$limit->consume(1)->isAccepted()) {
            return $this->problem(429, 'Too Many Requests', 'Rate limit exceeded');
        }
        $auth = (string)$request->headers->get('Authorization', '');
        $cid  = $cs = null;
        if (str_starts_with($auth, 'Basic ')) {
            $pair       = base64_decode(substr($auth, 6)) ?: ':';
            [$cid, $cs] = explode(':', $pair, 2);
        }
        $data         = json_decode($request->getContent(), true) ?? [];

        // Validate request data (only if not using Basic auth)
        if (!$cid) {
            $violations = $this->validator->validate($data, new Assert\Collection(
                fields: [
                    'client_id'     => new Assert\Required([new Assert\NotBlank()]),
                    'client_secret' => new Assert\Required([new Assert\NotBlank()]),
                    'org'           => new Assert\Required([new Assert\NotBlank(), new Assert\Uuid()]),
                    'scope'         => new Assert\Optional([
                        new Assert\Type('array'),
                        new Assert\Count(max: 10),
                        new Assert\All([new Assert\Regex('/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)*$/')])
                    ]),
                ],
                allowExtraFields: true,
            ));
            if (count($violations)) {
                return $this->problem(400, 'Bad Request', 'Invalid request body');
            }
        } else {
            // Basic auth - only validate org and scope
            $violations = $this->validator->validate($data, new Assert\Collection(
                fields: [
                    'org'   => new Assert\Required([new Assert\NotBlank(), new Assert\Uuid()]),
                    'scope' => new Assert\Optional([
                        new Assert\Type('array'),
                        new Assert\Count(max: 10),
                        new Assert\All([new Assert\Regex('/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)*$/')])
                    ]),
                ],
                allowExtraFields: true,
                allowMissingFields: true,
            ));
            if (count($violations)) {
                return $this->problem(400, 'Bad Request', 'Invalid request body');
            }
        }

        $clientId     = $cid ?: (string)($data['client_id'] ?? '');
        $clientSecret = $cs ?: (string)($data['client_secret'] ?? '');
        $orgId        = (string)($data['org'] ?? '');
        $reqScopes    = array_values(array_unique(array_map('strval', $data['scope'] ?? [])));

        $client = $this->clients->findActiveByClientId($clientId);
        if (!$client instanceof OAuthClient) {
            return $this->problem(401, 'Unauthorized', 'Invalid client');
        }
        $hasher = $this->hasherFactory->getPasswordHasher(OAuthClient::class);
        if (!$hasher->verify($client->getSecretHash(), $clientSecret)) {
            return $this->problem(401, 'Unauthorized', 'Invalid client secret');
        }
        if ($orgId === '') {
            return $this->problem(400, 'Bad Request', 'Missing org');
        }
        if ($client->getAllowedOrgs() !== null && !in_array($orgId, $client->getAllowedOrgs(), true)) {
            return $this->problem(401, 'Unauthorized', 'Org not allowed');
        }
        $allowed = $client->getAllowedScopes() ?? [];
        foreach ($reqScopes as $s) {
            if (!in_array($s, $allowed, true)) {
                return $this->problem(401, 'Unauthorized', 'Scope not allowed');
            }
        }
        $org = $this->orgs->find($orgId);
        if (!$org instanceof Organization) {
            return $this->problem(400, 'Bad Request', 'Unknown org');
        }
        $access = $this->tokens->createAccessTokenForClient($client, $org, $reqScopes);
        return $this->json([
            'access_token' => $access,
            'expires_in'   => $this->tokens->getTtl(),
            'token_type'   => 'Bearer',
        ]);
    }

    private function problem(int $status, string $title, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type'   => 'about:blank',
            'title'  => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status);
    }
}
