# Contributing to Orthrus

Thank you for your interest in contributing to Orthrus! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Code Standards](#code-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Reporting Issues](#reporting-issues)

## Code of Conduct

This project adheres to a code of conduct that ensures a welcoming environment for all contributors. By participating, you are expected to uphold professional standards and treat all participants with respect.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Docker
- OpenSSL
- Git

### Development Setup

1. **Fork and clone the repository**
   ```bash
   git clone https://github.com/Thavarshan/orthrus.git
   cd orthrus
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Generate RSA keys**
   ```bash
   make keygen
   ```

4. **Start development environment**
   ```bash
   make up
   make migrate
   make seed
   ```

5. **Run the development server**
   ```bash
   symfony server:start -d
   ```

## Development Workflow

### Branching Strategy

- `main` branch contains production-ready code
- Feature branches should be created from `main`
- Use descriptive branch names: `feature/refresh-token-rotation`, `fix/jwt-validation-bug`

### Development Process

1. Create a feature branch from `main`
2. Make your changes following the code standards
3. Add or update tests as necessary
4. Run the full test suite and quality checks
5. Submit a pull request

## Code Standards

### Code Style

This project uses PHP-CS-Fixer to enforce PSR-12 coding standards with additional rules:

```bash
# Check code style
make lint

# Fix code style issues
make lint-fix
```

### Static Analysis

PHPStan is configured at level 8 for strict type checking:

```bash
# Run static analysis
make phpstan
```

### Quality Pipeline

Before submitting changes, run the complete quality pipeline:

```bash
# Run all checks (lint + phpstan + tests)
make check
```

### Coding Guidelines

- **Type Declarations**: Use strict type declarations in all new files
- **Return Types**: Always declare return types for methods
- **Docblocks**: Add docblocks for complex methods and class properties
- **Security**: Never commit sensitive data or hardcode secrets
- **Performance**: Consider performance implications of database queries
- **Error Handling**: Use appropriate exception types and error messages

## Testing

### Test Requirements

- All new features must include unit tests
- Maintain or improve existing test coverage
- Tests should be isolated and not depend on external services
- Use descriptive test method names that explain the scenario

### Running Tests

```bash
# Run all tests
make test

# Run specific test suite
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Functional

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Test Structure

- **Unit Tests**: Test individual classes and methods in isolation
- **Functional Tests**: Test complete request/response cycles
- **Integration Tests**: Test interactions between components

### Writing Tests

Example test structure:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testMethodName(): void
    {
        // Arrange
        $input = 'test input';

        // Act
        $result = $this->service->methodUnderTest($input);

        // Assert
        $this->assertSame('expected output', $result);
    }
}
```

## Submitting Changes

### Pull Request Process

1. **Update documentation** if your changes affect the API or configuration
2. **Add tests** for new functionality
3. **Run quality checks** to ensure code meets standards
4. **Write clear commit messages** following conventional commit format
5. **Submit pull request** with detailed description

### Commit Message Format

Use conventional commit format for clear commit history:

```
type(scope): description

Examples:
feat(auth): add refresh token rotation
fix(jwt): resolve token expiration validation
docs(api): update authentication endpoint documentation
test(token): add unit tests for token service
```

### Pull Request Template

When submitting a pull request, include:

- **Summary**: Brief description of changes
- **Motivation**: Why these changes are necessary
- **Testing**: How the changes were tested
- **Breaking Changes**: Any backward compatibility concerns
- **Documentation**: Links to updated documentation

## Reporting Issues

### Issue Guidelines

When reporting issues, please include:

- **Environment details**: PHP version, OS, dependency versions
- **Steps to reproduce**: Clear, numbered steps
- **Expected behavior**: What should happen
- **Actual behavior**: What actually happens
- **Error messages**: Full error output or logs
- **Additional context**: Any relevant configuration or setup details

### Issue Templates

Use the appropriate issue template:

- **Bug Report**: For reporting bugs or unexpected behavior
- **Feature Request**: For proposing new functionality
- **Security Issue**: For reporting security vulnerabilities (see SECURITY.md)

### Security Issues

**Do not report security vulnerabilities through public GitHub issues.**

Please refer to our [Security Policy](SECURITY.md) for responsible disclosure procedures.

## Project Structure

Understanding the codebase structure helps with contributions:

```
src/
├── Controller/     # HTTP request handlers
├── Entity/         # Doctrine entities
├── Repository/     # Database access layer
├── Security/       # Authentication and authorization
└── Service/        # Business logic services

tests/
├── Unit/           # Isolated unit tests
└── Functional/     # End-to-end API tests

config/
├── packages/       # Symfony bundle configuration
└── routes/         # Route definitions
```

## Getting Help

- **Documentation**: Check the README and inline documentation
- **Issues**: Search existing issues before creating new ones
- **Code Review**: Ask for clarification during the review process

Thank you for contributing to make this project better!