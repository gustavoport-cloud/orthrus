# Repository Guidelines

## Project Structure & Modules
- `src/` — Symfony 7 code under `App\`: Controllers, Security (JWT auth, voter), Services (Token/JWKS/Refresh), Entities, Repositories, Command.
- `config/` — Framework, security, doctrine, migrations, rate limiter, JWT params (`config/jwt.yaml`).
- `migrations/` — Doctrine migrations. Run after schema changes.
- `public/` — Front controller `index.php`.
- `bin/` — Symfony console (`bin/console`).
- `var/keys/` — RSA keys (`private.pem`, `public.pem`), gitignored; `kid` stored in `config/jwt.yaml`.

## Build, Run, and Test
- Install: `composer install`
- Generate keys: `make keygen`
- Start DB (Docker): `make up`
- Migrate: `make migrate`
- Seed demo: `make seed`
- Run API: `symfony server:start -d`
- Tests: `make test` (PHPUnit in `tests/Unit` and `tests/Functional`).

## Coding Style & Conventions
- PSR-12, 4 spaces, LF; keep strict types and typed properties.
- Namespaces map PSR-4 (`App\` → `src/`). Controllers end with `Controller`; services with `Service` when helpful.
- Routes use attributes; secure protected routes with `#[IsGranted('scope:...')]` and custom voter.

## Auth & Tenancy Expectations
- Access tokens: JWT RS256 (TTL configurable), claims include `org` and space-separated `scope`.
- Multi-tenancy: header `X-Org-Id` must equal token `org` (enforced in authenticator).
- JWKS at `GET /.well-known/jwks.json` with `kid` for rotation.
- Rate limiting: POST `/login` uses limiter `login`; POST `/token` uses `client_token`.

## Testing Guidelines
- Unit: focus on Token/JWKS/Refresh services. Functional: happy-path login/refresh, org mismatch, client credentials.
- Test names: `*Test.php`; mirror `src/` structure under `tests/Unit` and `tests/Functional`.

## Commits & PRs
- Use clear, imperative messages (e.g., `Add refresh rotation reuse detection`).
- Keep PRs small; include rationale, testing steps, and any curl examples. Link issues (`Closes #123`).

## Security Notes
- Never commit secrets or private keys. Use `.env.local` and Symfony Secrets for prod.
- Log minimally (no secrets). Validate input DTOs; return RFC7807-style JSON errors.
