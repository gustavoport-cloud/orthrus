# Copilot Instructions for This Repository

This repo is a production-ready, multi-tenant token service API built on Symfony 7 (PHP 8.2+). It issues RS256 JWT access tokens, supports refresh-token rotation with reuse detection, and client-credentials (M2M). Doctrine ORM is used for persistence. No large auth bundles; use lcobucci/jwt directly.

## Purpose & Scope
- Stateless JWT access tokens (10 min TTL) with org claim (`org`) and scopes (`scope` space-separated).
- Refresh tokens are opaque `<id>.<secret>`, hashed with Argon2id; rotation + reuse detection.
- Multi-tenancy is enforced by matching header `X-Org-Id` with token `org` (via a security voter, not the authenticator).
- Minimal endpoints only: `/login`, `/token/refresh`, `/logout`, `/token` (client credentials), `/.well-known/jwks.json`, `/me`.

## Core Components (do not rename)
- Entities: `User`, `Organization`, `Membership`, `OAuthClient`, `RefreshToken`, `RevokedJti` (optional helper).
- Repositories: typed ServiceEntityRepositories (with generics in phpdocs).
- Services:
  - `TokenService` (Lcobucci JWT v5): build/verify RS256 tokens. Kid from env `JWT_KID`.
  - `RefreshTokenService`: issue/rotate/revoke opaque refresh tokens.
  - `JwksService`: JWKS from PEM public key(s).
- Security:
  - `JwtAuthenticator`: verifies signature and claims (iss/aud/nbf/iat/exp). Does NOT enforce org.
  - `ScopeVoter`: checks `scope:*` attributes.
  - `OrgVoter`: checks header `X-Org-Id` equals token `org`.
- Controllers: `AuthController`, `JwksController`, `MeController` (attribute routes).

## Configuration & Keys
- JWT config: `config/jwt.yaml` (issuer, audience, ttl, skew, key paths, `kid` from `JWT_KID`).
- Keys live under `var/keys/` and are gitignored. Use `make keygen` (writes/updates `JWT_KID` in `.env.local`).
- Env defaults: APP_URL, DATABASE_URL (MySQL by default), JWT_KID.

## Conventions & Style
- PSR-12, strict types, typed properties, constructor DI.
- Names: Controllers end with `Controller`, voters with `Voter`, services with `Service`.
- Doctrine mapping via attributes in `src/` (auto-mapped). UUIDs as strings.
- Return RFC7807-style JSON for errors: `{type, title, status, detail}`.
- Keep code minimal; avoid refactors outside the change scope.

## Security Rules (important)
- Authenticator extracts Bearer token, verifies RS256 signature, validates iss/aud/exp/nbf with skew. Do NOT check org here.
- Route-level security:
  - `/me` requires `#[IsGranted('scope:profile.read')]` and `#[IsGranted('org:matches')]`.
  - Add scopes via `#[IsGranted('scope:<scope.name>')]` and use `ScopeVoter`.
- Rate limiting: `login` and `token` POSTs—see `config/packages/rate_limiter.yaml`.

## Persistence
- RefreshToken: either `user_id` XOR `client_id` is set; indexes as defined in migration. Token secrets are hashed (Argon2id).
- Prefer repository methods; use parameterized queries only.

## Error Handling
- Use the `problem()` helper in controllers for error JSON.
- 401 for invalid credentials or tokens; 403 for scope/org authorization failures.

## Adding Features (pattern)
1. Define request DTO/validation with `Symfony\Validator` (in controller).
2. Apply rate limiter if public POST.
3. Implement logic in a service (inject via DI). Keep controllers thin.
4. Enforce authZ via `#[IsGranted('scope:...')]` and voters.
5. Return problem+json for errors and JSON for success.
6. Add unit tests (service-level) and functional tests (endpoints) using the patterns in `tests/`.
7. Update `openapi.yaml` examples and README snippets.

## Testing
- PHPUnit tests under `tests/Unit` and `tests/Functional`.
- SQLite for tests; SchemaTool resets DB in functional tests. Keys are generated per-run.
- To test new endpoints: follow `FullApiFlowTest` patterns with `requestJson()` helper.

## CI/CD & Deployment
- CI: GitHub Actions (`.github/workflows/ci.yml`) runs PHPStan and PHPUnit.
- Deploy: GitHub Actions webhook to Forge (`deploy.yml`). Forge script at `.forge/deploy.sh` handles Composer, keys, JWT_KID, migrations, cache.

## Do / Don’t
- Do:
  - Keep types explicit; add phpdoc for array shapes/value types where needed (PHPStan).
  - Use `TokenService` for all JWT ops and `RefreshTokenService` for refresh flows.
  - Update `openapi.yaml` and README when changing behavior.
- Don’t:
  - Don’t commit secrets or keys; don’t hardcode `kid`.
  - Don’t add heavy auth bundles or switch to Laravel/Passport.
  - Don’t enforce multi-tenancy in the authenticator—use `OrgVoter`.

## Quick Reference Snippets
- Controller pattern (JSON + Validator + problem response):
```php
#[Route(path: '/example', methods: ['POST'])]
public function example(Request $req, ValidatorInterface $v): Response {
  $data = json_decode($req->getContent(), true) ?? [];
  $violations = $v->validate($data, new Assert\Collection(
    fields: ['name' => new Assert\Required([new Assert\NotBlank()])],
    allowExtraFields: true,
  ));
  if (count($violations)) { return $this->problem(400,'Bad Request','Invalid request body'); }
  return $this->json(['ok' => true]);
}
```
- Issue user token:
```php
$jwt = $tokenService->createAccessTokenForUser($user, $org, ['profile.read']);
```
- Verify + parse claims:
```php
$claims = $tokenService->verifyAndParse($jwt);
// $claims->sub, $claims->org, $claims->scopes
```
- Refresh rotation:
```php
['refresh' => $entity, 'plain' => $token] = $refreshTokenService->issue($userOrClient, $org, $ip, $ua);
['refresh' => $new, 'plain' => $newPlain] = $refreshTokenService->rotate($plain, $orgId);
```
- Voter usage on routes:
```php
#[IsGranted('scope:profile.read')]
#[IsGranted('org:matches')]
```

## When in Doubt
- Match existing patterns in controllers/services/voters.
- Keep behavior in sync with `openapi.yaml` and tests. If modifying behavior, update both.
- Prefer small, focused changes with corresponding tests.
