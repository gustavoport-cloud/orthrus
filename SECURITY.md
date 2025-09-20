# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |

## Reporting a Vulnerability

We take the security of Orthrus seriously. If you believe you have found a security vulnerability, please report it to us as described below.

### Responsible Disclosure

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please send an email to [thavarshan@gmail.com](mailto:thavarshan@gmail.com) with the following information:

- **Type of issue** (e.g., buffer overflow, SQL injection, cross-site scripting, etc.)
- **Full paths of source file(s)** related to the manifestation of the issue
- **The location of the affected source code** (tag/branch/commit or direct URL)
- **Any special configuration required** to reproduce the issue
- **Step-by-step instructions to reproduce** the issue
- **Proof-of-concept or exploit code** (if possible)
- **Impact of the issue**, including how an attacker might exploit the issue

This information will help us triage your report more quickly.

### Response Timeline

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours
- **Initial Assessment**: We will provide an initial assessment within 5 business days
- **Status Updates**: We will keep you informed of our progress throughout the investigation
- **Resolution**: We aim to resolve security issues within 30 days when possible

### Disclosure Policy

When we receive a security bug report, we will:

1. Confirm the problem and determine the affected versions
2. Audit code to find any potential similar problems
3. Prepare a fix for all supported releases
4. Release new versions with the fix
5. Publicly announce the security fix

We request that you give us reasonable time to address the issue before making any information public.

## Security Considerations

### Authentication & Authorization

This application implements OAuth 2.0 and JWT-based authentication with the following security measures:

#### JWT Tokens
- **Algorithm**: RS256 (RSA with SHA-256)
- **Key Length**: 4096-bit RSA keys
- **TTL**: 15 minutes (configurable)
- **Storage**: Stateless, signed tokens
- **Revocation**: JTI-based blacklisting for compromised tokens

#### Refresh Tokens
- **Format**: UUID.cryptographic_secret
- **Storage**: Argon2id hashed secrets in database
- **Rotation**: Automatic rotation on each use
- **Reuse Detection**: Family invalidation on suspicious activity
- **TTL**: Configurable (default: 30 days)

#### Multi-tenancy
- **Isolation**: Complete organization-level data separation
- **Validation**: Required X-Org-Id header on protected endpoints
- **Scope Control**: OAuth 2.0 scope-based authorization

### Rate Limiting

The application implements rate limiting on authentication endpoints:

- **Login endpoint**: IP-based throttling
- **Token endpoint**: IP-based throttling
- **Framework**: Symfony Rate Limiter component

### Key Management

#### RSA Key Pairs
- **Generation**: OpenSSL 4096-bit keys
- **Storage**: Local filesystem in `var/keys/` (gitignored)
- **Rotation**: Manual process with JWKS update
- **Discovery**: Public keys available at `/.well-known/jwks.json`

#### Security Best Practices
- Keys are excluded from version control
- Private keys have restricted file permissions
- JWKS endpoint includes proper cache headers

### Database Security

#### Connection Security
- **Production**: PostgreSQL with TLS encryption
- **Development**: Docker containerized PostgreSQL
- **Testing**: SQLite in-memory for isolation

#### Data Protection
- **Password Hashing**: PHP password_hash() with default algorithm
- **Refresh Token Secrets**: Argon2id hashing
- **Sensitive Data**: No plaintext storage of secrets

### Input Validation

- **Request Validation**: Symfony Validator component
- **Type Safety**: PHP 8.2+ strict typing
- **Sanitization**: Doctrine ORM parameter binding
- **Output Encoding**: JSON response encoding

### Environment Security

#### Configuration
- **Environment Variables**: Sensitive configuration via .env files
- **Secrets Management**: No hardcoded secrets in source code
- **Docker**: Containerized services with network isolation

#### Development vs Production
- **Debug Mode**: Disabled in production
- **Error Reporting**: Sanitized error responses
- **Logging**: Configurable log levels

### Known Security Features

1. **CORS**: Configurable cross-origin resource sharing
2. **HTTPS**: TLS encryption for all communications (recommended)
3. **Content Security Policy**: Configurable HTTP security headers
4. **SQL Injection Prevention**: Doctrine ORM parameter binding
5. **XSS Prevention**: JSON API responses, no HTML rendering

### Security Monitoring

We recommend implementing the following monitoring practices:

#### Authentication Monitoring
- Failed login attempt tracking
- Unusual access pattern detection
- Geographic access anomalies
- Multiple refresh token usage

#### System Monitoring
- Database connection monitoring
- Rate limit breach alerts
- Error rate monitoring
- Resource usage tracking

### Security Checklist for Deployment

#### Infrastructure
- [ ] HTTPS/TLS enabled for all endpoints
- [ ] Database connections encrypted
- [ ] Network firewalls configured
- [ ] Access logging enabled

#### Application
- [ ] Debug mode disabled
- [ ] Error reporting configured for production
- [ ] Rate limiting enabled
- [ ] CORS policies configured

#### Keys and Secrets
- [ ] RSA keys generated with proper entropy
- [ ] Environment variables secured
- [ ] File permissions restricted on key files
- [ ] Database credentials rotated

#### Monitoring
- [ ] Security event logging enabled
- [ ] Failed authentication tracking
- [ ] Anomaly detection configured
- [ ] Incident response procedures documented

## Security Updates

Security updates will be clearly marked in release notes and will include:

- **CVE number** (if applicable)
- **Severity level** (Critical, High, Medium, Low)
- **Affected versions**
- **Mitigation steps**
- **Upgrade instructions**

Subscribe to releases on GitHub to receive notifications of security updates.

## Security Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://phpsec.org/)
- [Symfony Security](https://symfony.com/doc/current/security.html)
- [JWT Security Best Practices](https://tools.ietf.org/html/rfc8725)
- [OAuth 2.0 Security Best Current Practice](https://tools.ietf.org/html/draft-ietf-oauth-security-topics)

## Thank You

We appreciate your efforts to responsibly disclose security vulnerabilities and help us maintain the security of this project and our users.