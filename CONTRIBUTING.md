# Contributing to AG-VOTE

Thank you for your interest in contributing to AG-VOTE. This document outlines the coding conventions and best practices for this project.

## Table of Contents

- [Getting Started](#getting-started)
- [Code Style](#code-style)
  - [PHP](#php)
  - [JavaScript](#javascript)
  - [CSS](#css)
- [Architecture](#architecture)
- [API Conventions](#api-conventions)
- [Testing](#testing)
- [Commit Messages](#commit-messages)

---

## Getting Started

1. Clone the repository
2. Copy `.env.example` to `.env` and configure
3. Run `composer install`
4. Initialize the database with `database/schema-master.sql`
5. Start local server: `php -S localhost:8000 -t public`

See [SETUP.md](SETUP.md) for detailed instructions.

---

## Code Style

### General

- **Language**: All code, comments, and documentation must be in **English**
- **Encoding**: UTF-8
- **Line endings**: LF (Unix-style)
- **Final newline**: Always include a trailing newline

### PHP

PHP code follows **PSR-12** with additional rules defined in `.php-cs-fixer.dist.php`.

#### Namespaces

All classes must use the `AgVote\*` namespace with PSR-4 autoloading:

```php
<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

class AuthMiddleware {
    // ...
}
```

#### Formatting

- **Indentation**: 4 spaces
- **Braces**: Opening brace on same line (K&R style)
- **Arrays**: Short syntax `[]` only
- **Strings**: Single quotes preferred
- **Trailing commas**: Always in multiline arrays/arguments

```php
// Good
$config = [
    'debug' => true,
    'cache' => false,
];

// Bad
$config = array(
    "debug" => true,
    "cache" => false
);
```

#### PHPDoc

Document all public methods and complex logic:

```php
/**
 * Validate user input against a schema.
 *
 * @param array<string, mixed> $data Input data to validate
 * @param string $schemaName Schema identifier from ValidationSchemas
 *
 * @throws ValidationException When validation fails
 *
 * @return array<string, mixed> Validated and sanitized data
 */
public function validate(array $data, string $schemaName): array {
    // ...
}
```

#### Run Linter

```bash
# Check
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix automatically
vendor/bin/php-cs-fixer fix
```

### JavaScript

JavaScript follows standard ES6+ conventions defined in `.eslintrc.json`.

#### Formatting

- **Indentation**: 2 spaces
- **Semicolons**: Required
- **Quotes**: Single quotes preferred
- **Variables**: Use `const` by default, `let` when reassignment needed, never `var`
- **Trailing commas**: Always in multiline

```javascript
// Good
const config = {
  apiUrl: '/api',
  timeout: 5000,
};

// Bad
var config = {
  "apiUrl": "/api",
  "timeout": 5000
}
```

#### JSDoc

Document all functions and modules:

```javascript
/**
 * Fetch data from API endpoint.
 *
 * @param {string} endpoint - API endpoint path
 * @param {Object} [options={}] - Fetch options
 * @param {string} [options.method='GET'] - HTTP method
 * @param {Object} [options.body] - Request body for POST/PUT
 * @returns {Promise<Object>} API response data
 * @throws {Error} When API returns error response
 */
async function fetchApi(endpoint, options = {}) {
  // ...
}
```

#### Global Objects

The following globals are available (do not redeclare):

- `MeetingContext` - Meeting ID singleton
- `Utils` - Utility functions
- `Shared` - Shared state/functions
- `AgToast` - Toast notification system
- `api()` - API helper function
- `htmx` - HTMX library

#### Run Linter

```bash
npx eslint public/assets/js/
```

### CSS

CSS follows the standard configuration in `.stylelintrc.json`.

#### Design System

Use the established design system classes:

**Buttons:**
```html
<!-- Correct -->
<button class="btn btn-primary">Submit</button>
<button class="btn btn-ghost btn-sm">Cancel</button>

<!-- Incorrect (legacy) -->
<button class="btn primary">Submit</button>
```

**Typography:**
```html
<!-- Correct -->
<p class="text-sm text-muted">Small muted text</p>
<h1 class="text-2xl font-bold">Title</h1>

<!-- Incorrect (legacy) -->
<p class="tiny muted">Small muted text</p>
<h1 class="h1">Title</h1>
```

**Colors:** Use CSS custom properties:
```css
.my-component {
  background: var(--bg-card);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}
```

#### Run Linter

```bash
npx stylelint "public/assets/css/**/*.css"
```

---

## Architecture

### Directory Structure

```
app/
├── Core/
│   ├── Security/      # Auth, CSRF, Rate limiting
│   └── Validation/    # Input validation
├── Repository/        # Database access layer
├── Services/          # Business logic
├── api.php           # API helpers
└── bootstrap.php     # Application entry point

public/
├── assets/
│   ├── css/          # Stylesheets
│   └── js/           # Client-side scripts
└── index.php         # Public entry point

database/
├── migrations/       # Database migrations
├── seeds/           # Test data
└── schema-master.sql # Master schema
```

### Backend Patterns

**Repository Pattern**: All database access goes through Repository classes:

```php
// Good
$meeting = $meetingRepo->findById($id);

// Bad - direct DB access in controllers
$meeting = $pdo->query("SELECT * FROM meetings...")->fetch();
```

**Tenant Isolation**: Always include tenant_id in queries:

```php
// Good
public function findByIdForTenant(string $id, int $tenantId): ?Meeting {
    // ...
}

// Dangerous - no tenant check
public function findById(string $id): ?Meeting {
    // ...
}
```

### Frontend Patterns

**Meeting Context**: Always use MeetingContext for meeting_id:

```javascript
// Good
const meetingId = MeetingContext.get();

// Bad - direct localStorage/URL access
const meetingId = localStorage.getItem('meeting_id');
```

**Notifications**: Use AgToast for all notifications:

```javascript
// Good
AgToast.success('Operation completed');
AgToast.error('Something went wrong');

// Deprecated (but works - delegates to AgToast)
setNotif('Operation completed', 'success');
```

**WebSocket/Polling**: Check WebSocket status before polling:

```javascript
// Good
if (!window._wsClient?.isRealTime) {
  startPolling();
}
```

---

## API Conventions

### Response Format

All API responses follow this format:

```json
// Success
{
  "ok": true,
  "data": { ... }
}

// Error
{
  "ok": false,
  "error": "error_code",
  "message": "Human readable message"
}
```

### Error Codes

Use ErrorDictionary for consistent error messages:

```php
// Good
api_fail('not_found', 404);
api_fail('validation_error', 422);

// Bad - inline messages
api_fail('The item was not found', 404);
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad request (malformed)
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not found
- `422` - Validation error
- `500` - Server error

---

## Testing

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific test file
vendor/bin/phpunit tests/Unit/VoteEngineTest.php

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Test Structure

```
tests/
├── Unit/           # Unit tests (isolated)
├── Integration/    # Integration tests (with DB)
└── fixtures/       # Test data
```

### Writing Tests

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgVote\Services\VoteEngine;

class VoteEngineTest extends TestCase {
    public function testCalculateResults(): void {
        $engine = new VoteEngine();
        $result = $engine->calculate([...]);

        $this->assertEquals('approved', $result['status']);
    }
}
```

---

## Commit Messages

Follow conventional commit format:

```
type(scope): short description

Longer description if needed.
```

**Types:**
- `feat` - New feature
- `fix` - Bug fix
- `refactor` - Code refactoring
- `docs` - Documentation
- `test` - Tests
- `chore` - Maintenance

**Examples:**
```
feat(voting): add weighted vote calculation
fix(auth): handle expired session tokens
refactor(api): centralize error handling
docs: update API documentation
test(quorum): add edge case tests
```

---

## Questions?

If you have questions about these conventions, please open an issue or contact the maintainers.
