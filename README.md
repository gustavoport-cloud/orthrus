# Orthrus

A production-ready, multi-tenant JWT authentication service built with Symfony 7.3 and PHP 8.2+. Provides secure RS256 JWT access tokens, refresh token rotation with reuse detection, OAuth 2.0 client credentials flow, and scope-based authorization.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [API Reference](#api-reference)
- [Authentication](#authentication)
- [Security](#security)
- [Development](#development)
- [Testing](#testing)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Multi-tenant Architecture**: Complete organization isolation with tenant-scoped operations
- **JWT Access Tokens**: RS256-signed tokens with 10-minute TTL (configurable) and RS256 signature
- **Refresh Token Rotation**: Automatic rotation with reuse detection for enhanced security
- **OAuth 2.0 Client Credentials**: Machine-to-machine authentication flow
- **Scope-based Authorization**: Fine-grained access control with scope validation
- **Rate Limiting**: Built-in protection against brute force attacks
- **JWKS Support**: Public key discovery endpoint for token verification
- **Reuse Detection**: Automatic refresh token family invalidation on suspicious activity

## Requirements

- PHP 8.2 or higher
- Composer
- Symfony CLI
- Docker (for PostgreSQL)
- OpenSSL (for key generation)

## Quick Start

1. **Install dependencies**

   ```bash
   composer install
   ```

2. **Generate RSA key pair and JWT key ID**

   ```bash
   make keygen
   ```

3. **Configure database**
   - Default: local MySQL (edit `DATABASE_URL` in `.env` or `.env.local`)
   - Optional: start PostgreSQL via Docker: `make up`

5. **Run database migrations**

   ```bash
   make migrate
   ```

6. **Seed demo data**

   ```bash
   make seed
   ```

7. **Start development server**

   ```bash
   symfony server:start -d
   ```

The API will be available at `http://localhost:8000`

## Architecture

### Core Entities

- **User**: Application users with email/password authentication
- **Organization**: Multi-tenant boundary for complete data isolation
- **Membership**: User-organization relationships with role-based access
- **OAuthClient**: OAuth 2.0 clients for machine-to-machine authentication
- **RefreshToken**: Opaque tokens with automatic rotation and family tracking
- **RevokedJti**: Blacklist for revoked JWT token identifiers

### Authentication Flow

1. **User Authentication**: Email/password login with organization context
2. **Token Issuance**: Access token (JWT) + refresh token (opaque) pair
3. **Token Refresh**: Automatic rotation of refresh tokens with reuse detection
4. **Client Credentials**: Direct client authentication for service-to-service communication

### Security Model

- **JWT Tokens**: RS256 signature, short-lived (10 minutes), stateless verification
- **Refresh Tokens**: Long-lived, server-side validation, automatic rotation
- **Multi-tenancy**: Organization-scoped access with header validation
- **Scope Authorization**: Granular permissions using OAuth 2.0 scopes
- **Rate Limiting**: IP-based throttling on authentication endpoints

## API Reference

### Authentication Endpoints

#### POST /login

User authentication with email and password.

**Request:**

```json
{
  "email": "user@example.com",
  "password": "password",
  "org": "organization-uuid",
  "scope": ["profile.read", "data.write"]
}
```

**Response:**

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "expires_in": 600,
  "refresh_token": "uuid.secret",
  "token_type": "Bearer"
}
```

**Error Responses:**

- `400 Bad Request`: Invalid request body or validation errors
- `401 Unauthorized`: Invalid credentials or user not in organization
- `429 Too Many Requests`: Rate limit exceeded

#### POST /token/refresh

Refresh an expired access token using a refresh token.

**Request:**

```json
{
  "refresh_token": "uuid.secret",
  "org": "organization-uuid"
}
```

**Response:**

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "expires_in": 600,
  "refresh_token": "new-uuid.new-secret",
  "token_type": "Bearer"
}
```

**Error Responses:**

- `400 Bad Request`: Invalid request body
- `401 Unauthorized`: Invalid or expired refresh token

#### POST /logout

Revoke a refresh token (logout).

**Request:**

```json
{
  "refresh_token": "uuid.secret"
}
```

**Response:**

- `204 No Content`: Token successfully revoked

#### POST /token

OAuth 2.0 client credentials flow for machine-to-machine authentication.

**Request (Basic Auth):**

```bash
curl -X POST http://localhost:8000/token \
  -u "client-id:client-secret" \
  -H "Content-Type: application/json" \
  -d '{"org": "organization-uuid", "scope": ["api.read"]}'
```

**Request (JSON Body):**

```json
{
  "client_id": "demo-client",
  "client_secret": "secret",
  "org": "organization-uuid",
  "scope": ["api.read", "api.write"]
}
```

**Response:**

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "expires_in": 600,
  "token_type": "Bearer"
}
```

**Error Responses:**

- `400 Bad Request`: Missing organization or invalid request
- `401 Unauthorized`: Invalid client credentials or unauthorized scope/organization
- `429 Too Many Requests`: Rate limit exceeded

### Protected Endpoints

#### GET /me

Get current user/client information. Requires valid access token and organization header.

**Headers:**

```
Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
X-Org-Id: organization-uuid
```

**Response:**

```json
{
  "sub": "user:user-uuid" | "client:client-uuid",
  "org": "organization-uuid",
  "scopes": ["profile.read"],
  "client": false | true
}
```

**Error Responses:**

- `401 Unauthorized`: Invalid or expired token
- `403 Forbidden`: Missing or mismatched X-Org-Id header, or missing required scope `profile.read`

### Discovery Endpoints

#### GET /.well-known/jwks.json

JSON Web Key Set (JWKS) for token verification.

**Response:**

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "kid": "key-id-uuid",
      "n": "base64-encoded-modulus",
      "e": "AQAB"
    }
  ]
}
```

**Cache Headers:**

- `Cache-Control: public, max-age=300`

## Authentication

### JWT Access Tokens

Access tokens are RS256-signed JWTs with the following structure:

**Header:**

```json
{
  "alg": "RS256",
  "typ": "JWT",
  "kid": "key-id-uuid"
}
```

**Payload (example):**

```json
{
  "iss": "http://localhost:8000",
  "aud": "symfony-token-service",
  "sub": "user:uuid" | "client:uuid",
  "iat": 1234567890,
  "nbf": 1234567890,
  "exp": 1234567890,
  "jti": "token-uuid",
  "org": "organization-uuid",
  "scope": "profile.read data.write"
}
```

### Refresh Tokens

Refresh tokens use the format `<id>.<secret>` where:

- `id`: UUID identifying the token record
- `secret`: Cryptographically random string
- Storage: Argon2id hash of the secret in the database
- Rotation: New token issued on each refresh, old token invalidated
- Reuse Detection: Entire token family invalidated if reuse detected

### Multi-tenant Access

All protected endpoints require the `X-Org-Id` header to match the organization claim in the JWT token. This ensures complete tenant isolation.

### Scope Authorization

Use the `#[IsGranted('scope:profile.read')]` attribute on controller methods to enforce scope-based authorization.

## Security

### Rate Limiting

- **Login endpoint**: Limited by client IP address
- **Token endpoint**: Limited by client IP address
- **Configuration**: Uses Symfony Rate Limiter component

### Key Management

- **Location**: RSA key pairs stored in `var/keys/` (gitignored)
- **Generation**: 4096-bit RSA keys with `make keygen`
- **Rotation**: Current key ID stored in `config/jwt.yaml`
- **JWKS**: Public keys published at `/.well-known/jwks.json`

### Token Security

- **Access Token TTL**: 10 minutes (configurable)
- **Clock Skew**: Configurable tolerance for time differences
- **Signature**: RS256 algorithm with proper key validation
- **Revocation**: JTI-based blacklisting for compromised tokens

## Development

### Make Commands

```bash
make up          # Start PostgreSQL with Docker
make down        # Stop Docker containers
make keygen      # Generate RSA keys and JWT key ID
make migrate     # Create database and run migrations
make seed        # Seed demo data
make test        # Run PHPUnit tests
make lint        # Check code style (dry-run)
make lint-fix    # Fix code style issues
make phpstan     # Run static analysis
make check       # Run all quality checks (lint + phpstan + test)
make ci          # Alias for check (CI pipeline)
```

### Demo Data

The `make seed` command creates:

- **Organization**: Demo Org
- **User**: <user@example.com> / password
- **Client**: demo-client / secret
- **Scopes**: profile.read

### Environment Configuration

Key environment variables in `.env` or `.env.local`:

```bash
APP_URL=http://localhost:8000
DATABASE_URL="mysql://root@127.0.0.1:3306/symfony?serverVersion=8.0&charset=utf8mb4"
# JWT kid used in token headers and JWKS (generated via `make keygen`)
JWT_KID=change-me
```

## Testing

### Running Tests

```bash
# All tests
make test

# Specific test suite
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Functional

# Single test class
./vendor/bin/phpunit tests/Unit/TokenServiceTest.php
```

### Code Quality

#### PHPStan
Static analysis at level 8 for type safety and code quality:
- Type checking and inference
- Dead code detection
- Missing return types

```bash
# Run static analysis
make phpstan

# Generate baseline for existing issues
make phpstan-baseline
```

#### Quality Pipeline
```bash
# Run all quality checks
make check  # phpstan + test

# CI pipeline command
make ci     # alias for check
```

### Test Environment

- **Database**: SQLite in-memory for isolation
- **Configuration**: `phpunit.xml.dist`
- **Environment**: `APP_ENV=test`

## Configuration

### JWT Configuration

File: `config/jwt.yaml`

```yaml
parameters:
  jwt:
    issuer: '%env(JWT_ISSUER)%'
    audience: '%env(JWT_AUDIENCE)%'
    access_ttl: '%env(int:JWT_ACCESS_TTL)%'
    skew: '%env(int:JWT_SKEW)%'
    keys:
      current:
        kid: 'current-key-uuid'
        private_path: '%kernel.project_dir%/var/keys/private.pem'
        public_path: '%kernel.project_dir%/var/keys/public.pem'
```

### Security Configuration

File: `config/packages/security.yaml`

- JWT authenticator configuration
- Scope-based access control
- Firewall rules for API endpoints

### Database Configuration

File: `config/packages/doctrine.yaml`

- PostgreSQL connection for production
- SQLite for testing
- Entity mappings and migrations

## Contributing

We welcome contributions to Orthrus! Please read our [Contributing Guidelines](CONTRIBUTING.md) for details on:

- Development setup and workflow
- Code standards and quality checks
- Testing requirements
- Pull request process

For security vulnerabilities, please follow our [Security Policy](SECURITY.md) for responsible disclosure.

## License

This project is proprietary. See composer.json for license details.
