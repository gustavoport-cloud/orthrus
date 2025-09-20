# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview
Orthrus - Multi-tenant JWT token service built with Symfony 7.3 and PHP 8.2+. Provides secure authentication with RS256 JWT access tokens, refresh token rotation with reuse detection, and OAuth client credentials flow.

## Development Commands

### Setup
- `composer install` - Install PHP dependencies
- `make keygen` - Generate RSA key pair and JWT key ID
- `make up` - Start PostgreSQL database with Docker
- `make migrate` - Create database and run migrations
- `make seed` - Seed demo data (user, org, client)

### Development
- `symfony server:start -d` - Start development server (requires Symfony CLI)
- `make test` or `composer test` - Run PHPUnit tests
- `make down` - Stop Docker containers

### Database
- Database runs on port 5432 (PostgreSQL)
- Adminer available on port 8080 for database inspection
- Uses Doctrine ORM with migrations in `migrations/`

## Architecture

### Core Entities
- **User**: Basic user with email/password, belongs to organizations via memberships
- **Organization**: Multi-tenant isolation boundary
- **Membership**: Join table between users and organizations
- **OAuthClient**: Client credentials for machine-to-machine authentication
- **RefreshToken**: Opaque tokens with rotation and reuse detection
- **RevokedJti**: Tracks revoked JWT IDs for security

### Authentication Flow
1. **User Login**: POST `/login` with email/password/org → returns access + refresh tokens
2. **Token Refresh**: POST `/token/refresh` → rotates refresh token, returns new access token
3. **Client Credentials**: POST `/token` with client_id/secret → returns access token
4. **Protected Routes**: Require `Authorization: Bearer <token>` + `X-Org-Id` header

### Security Architecture
- **JWT Tokens**: RS256 signed, 15-minute TTL, include scopes and org context
- **Refresh Tokens**: Format `<id>.<secret>`, Argon2id hashed, automatic rotation
- **Scope Authorization**: Uses `#[IsGranted('scope:profile.read')]` attributes
- **Rate Limiting**: Applied to `/login` and `/token` endpoints
- **Multi-tenant**: Org isolation enforced via X-Org-Id header validation

### Key Services
- **TokenService**: JWT creation/verification, manages RSA keys and JWKS
- **RefreshTokenService**: Token rotation, reuse detection, revocation
- **JwtAuthenticator**: Symfony security authenticator for Bearer tokens
- **ScopeVoter**: Authorization voter for scope-based access control

### Configuration
- RSA keys stored in `var/keys/` (gitignored)
- Current key ID configured in `config/jwt.yaml`
- JWKS endpoint at `/.well-known/jwks.json` publishes public keys
- Multi-environment support via `.env` files

### Testing
- PHPUnit configuration in `phpunit.xml.dist`
- Separate test suites: Unit and Functional
- SQLite in-memory database for tests
- Test environment uses `APP_ENV=test`

## File Structure
- `src/Entity/` - Doctrine entities
- `src/Repository/` - Database repositories
- `src/Controller/` - HTTP controllers (Auth, JWKS, Me)
- `src/Security/` - Authentication and authorization components
- `src/Service/` - Business logic services
- `config/packages/` - Symfony bundle configurations
- `migrations/` - Database schema migrations