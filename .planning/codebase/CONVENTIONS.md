# Coding Conventions

**Analysis Date:** 2026-03-16

## Overview

AG-VOTE is a PHP 8.4 + vanilla JavaScript application. The two languages have
distinct, well-established conventions that are enforced by automated tooling:
PHP-CS-Fixer (`@PSR12` + `@PHP82Migration`) for PHP; ESLint v9 flat config
for JavaScript.

---

## PHP Conventions

### Strict Types

Every PHP file opens with `declare(strict_types=1)`. This is enforced project-wide
(134 files verified). No exceptions.

```php
<?php

declare(strict_types=1);

namespace AgVote\Controller;
```

### Namespace Structure

PSR-4 with two roots defined in `composer.json`:
- `AgVote\` → `app/`
- `AgVote\Service\` → `app/Services/`
- Test namespace: `AgVote\Tests\` → `tests/`

### Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Classes | PascalCase | `MeetingWorkflowService`, `BallotsController` |
| Methods | camelCase | `findByIdForTenant()`, `computeMotionResult()` |
| Properties | camelCase | `$this->meetingRepo`, `$tenantId` |
| Constants | UPPER_SNAKE_CASE | `DEFAULT_TENANT_ID`, `APP_SECRET` |
| DB columns | snake_case | `tenant_id`, `created_at` |
| Files | Match class name exactly | `MeetingRepository.php` |

### Code Style (PHP-CS-Fixer)

Config: `.php-cs-fixer.dist.php`

Key rules beyond `@PSR12` + `@PHP82Migration`:

```php
// Short array syntax
$ids = ['a', 'b', 'c'];

// Single quotes for strings
$label = 'Administrateur';

// Opening brace on same line as class/function
class FooService {
    public function bar(): void {
    }
}

// Trailing commas in multiline arrays, arguments, and parameters
$result = someFunction(
    $arg1,
    $arg2,
    $arg3,    // trailing comma required
);

// Ordered imports: class then function then const, alphabetical within each
use AgVote\Core\Http\ApiResponseException;
use AgVote\Core\Http\JsonResponse;
use AgVote\Repository\MemberRepository;
```

Commands:
```bash
vendor/bin/php-cs-fixer fix               # Fix in place
vendor/bin/php-cs-fixer fix --dry-run --diff  # Check only
make lint        # Alias for dry-run
make lint-fix    # Alias for fix
```

### PHPDoc Comments

Required on:
- Every class (purpose description)
- Complex return types (`@return array{key: type}`)
- Parameters needing domain clarification

```php
/**
 * Pure quorum/majority calculation. No I/O — takes all inputs as parameters.
 * Used by both computeMotionResult() and OfficialResultsService::decideWithPolicies().
 *
 * @return array{quorum: array, majority: array}
 */
public static function computeDecision(
    ?array $quorumPolicy,
    ?array $votePolicy,
    ...
): array { ... }
```

Inline comments use `//` (not `#`). PHP-CS-Fixer converts `#` to `//`.

Section separators use banner comments in both PHP and JS:
```php
// =========================================================================
// RESULT STRUCTURE TESTS
// =========================================================================
```

### Error Handling Pattern

`AbstractController::handle()` at `app/Controller/AbstractController.php`
wraps every controller call in centralized exception-to-response translation.
Controller methods contain **no try/catch**:

```php
// Controller methods: just throw
throw new RuntimeException('Meeting not found');
throw new InvalidArgumentException('Invalid UUID format');

// AbstractController maps exceptions to HTTP responses:
// InvalidArgumentException → 422 invalid_request
// PDOException             → 500 internal_error (with error_log)
// RuntimeException         → 400 business_error
// ApiResponseException     → propagated (normal API response flow)
// Throwable                → 500 internal_error (with error_log)
```

For cases where a callable may internally call `api_ok`/`api_fail`, use
`AbstractController::wrapApiCall()`:

```php
protected static function wrapApiCall(callable $fn, string $errorCode = 'internal_error', int $httpCode = 500): void
```

### API Response Pattern

Use global helper functions defined in `app/api.php`:

```php
api_ok(['items' => $members]);                    // 200 success
api_ok($data, 201);                               // 201 created
api_fail('not_found', 404);                       // error response
api_fail('invalid', 400, ['field' => 'email']);   // with extra context
```

These functions throw `ApiResponseException` — they never return. The Router
at `app/Core/Router.php` catches `ApiResponseException` and sends the JSON.

### Repository Pattern

All repositories extend `AbstractRepository` at `app/Repository/AbstractRepository.php`.
Repositories contain **no business logic** — only data access.

Constructor accepts optional `?PDO $pdo` for test injection (falls back to
global `db()`):

```php
abstract class AbstractRepository {
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? db();
    }

    // Shared helpers:
    protected function selectOne(string $sql, array $params = []): ?array
    protected function selectAll(string $sql, array $params = []): array
    protected function execute(string $sql, array $params = []): int
    protected function scalar(string $sql, array $params = []): mixed
    protected function insertReturning(string $sql, array $params = []): ?array
    protected function buildInClause(string $prefix, array $values, array &$params): string
}
```

### Service Dependency Injection

Services accept optional repository parameters, defaulting to
`RepositoryFactory::getInstance()` when not injected — enabling test injection
without a DI container:

```php
public function __construct(
    ?MotionRepository $motionRepo = null,
    ?BallotRepository $ballotRepo = null,
) {
    $this->motionRepo = $motionRepo ?? RepositoryFactory::getInstance()->motion();
    $this->ballotRepo = $ballotRepo ?? RepositoryFactory::getInstance()->ballot();
}
```

Controllers access repositories via `$this->repo()` which returns the shared
`RepositoryFactory` at `app/Core/Providers/RepositoryFactory.php`:

```php
$repo = $this->repo()->member();
$repo = $this->repo()->motion();
$repo = $this->repo()->ballot();
```

### Static Analysis

PHPStan at level 5. Config: `phpstan.neon`, baseline: `phpstan-baseline.neon`.
Global helpers (`api_*`, `db()`, `audit_log()`, `config()`) are whitelisted in
`phpstan.neon` `ignoreErrors` because they are defined at runtime.

---

## JavaScript Conventions

### Module Types: Two Coexisting Patterns

**Pattern 1 — IIFE (page scripts and core utilities)**
Used in `public/assets/js/pages/` and `public/assets/js/core/`.
Wrapped in `(function() { 'use strict'; ... })();` to avoid global scope pollution.
Module-level mutable state uses `var`; block-scoped logic uses `const`/`let`.
Public API exposed on `window`:

```js
(function() {
  'use strict';

  var _state = {};          // module-level state: var
  var O = window.OpS;       // reference to another module

  function init() { ... }
  function render() { ... }

  // Public interface
  window.OpS = { init, render };
})();
```

**Pattern 2 — Web Components (ES modules)**
Used in `public/assets/js/components/`.
Each component is a class extending `HTMLElement` with Shadow DOM.
Files use `export default` and are imported via `components/index.js`.
Also attached to `window` for global programmatic use:

```js
class AgModal extends HTMLElement {
  static get observedAttributes() { return ['title', 'size', 'closable']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._isOpen = false;        // private state: _prefixed
    this._previousFocus = null;
  }

  connectedCallback() { this.render(); }
  disconnectedCallback() { /* remove event listeners */ }
  attributeChangedCallback() { if (this.shadowRoot.innerHTML) this.render(); }
  open() { ... }
  close() { ... }
  render() { this.shadowRoot.innerHTML = `...`; }  // only safe place for innerHTML
}

customElements.define('ag-modal', AgModal);
window.AgModal = AgModal;
export default AgModal;
```

**Pattern 3 — Namespace extension (core utilities)**
`utils.js` and `shared.js` use a hybrid: IIFE that extends a `window` namespace:

```js
window.Utils = window.Utils || {};

(function(Utils) {
  'use strict';
  Utils.apiGet  = async function(url, options = {}) { ... };
  Utils.apiPost = async function(url, data = {}, options = {}) { ... };
})(window.Utils);
```

### Naming (JavaScript)

| Element | Convention | Example |
|---------|-----------|---------|
| Functions | camelCase | `showQuorumWarning()`, `renderAgendaList()` |
| Variables (const/let) | camelCase | `searchInput`, `currentFilter` |
| Variables (var) | camelCase | `allMembers`, `dragSrcIdx` |
| Module-level config | UPPER_SNAKE_CASE | `DRAFT_KEY`, `PIN_KEY` |
| Web Component classes | PascalCase + `Ag` prefix | `AgModal`, `AgToast` |
| Custom element names | `ag-*` kebab-case | `ag-modal`, `ag-toast` |
| Private instance props | `_prefixed` camelCase | `this._isOpen`, `this._dismissTimeout` |
| DOM IDs | camelCase | `meetingSelect`, `btnImport` |
| CSS classes | kebab-case | `app-sidebar`, `filter-chip` |

### ESLint Rules (v9 flat config)

Config: `eslint.config.mjs`

```
semi:                 error — semicolons required
quotes:               warn  — single quotes preferred
indent:               warn  — 2-space indent
eqeqeq:               warn  — prefer === over ==
no-undef:             error — no undeclared globals
no-unused-vars:       warn  — underscore prefix ignores pattern (_name)
agvote/no-inner-html: warn  — custom rule, must use escapeHtml() or textContent
```

The `agvote/no-inner-html` custom rule is defined inline in `eslint.config.mjs`.
It exempts `this.shadowRoot.innerHTML = ...` (Shadow DOM template initialization).

CI command: `npm run lint:ci` (allows up to 289 warnings — mostly innerHTML in
legacy page scripts being progressively migrated).

### XSS Protection

Do not use `innerHTML` with dynamic values. Use:
- `el.textContent = value` for plain text
- `el.innerHTML = escapeHtml(value)` for text that needs to appear in HTML

`escapeHtml()` is defined at multiple levels:
- Instance method in every Web Component: `escapeHtml(s) { ... }`
- Global function referenced in ESLint globals as `window.escapeHtml`
- Local function in IIFE page scripts

```js
// Correct
element.textContent = userInput;
element.innerHTML = escapeHtml(userInput);
this.shadowRoot.innerHTML = `<div>${this.escapeHtml(title)}</div>`;

// Incorrect — ESLint warns
element.innerHTML = userInput;
```

### localStorage Keys

All keys use a `ag-vote-` prefix:

```js
const PIN_KEY   = 'ag-vote-sidebar-pinned';
const DRAFT_KEY = 'ag-vote-wizard-draft';
```

### CustomEvent Naming

Events dispatched by Web Components use `ag-component-action` kebab format:

```js
this.dispatchEvent(new CustomEvent('ag-modal-open', { bubbles: true }));
this.dispatchEvent(new CustomEvent('ag-modal-close', { bubbles: true }));
```

---

## CSS Conventions

- **Design tokens**: CSS custom properties prefixed `--color-*`, `--radius-*`,
  `--shadow-*`, `--duration-*`. Defined in `public/assets/css/design-system.css`.
- **Theme support**: Dark mode via `[data-theme="dark"]` overrides on custom props.
- **No preprocessor**: Plain CSS throughout.
- **Per-page files**: Each page has its own CSS file (`admin.css`, `meetings.css`, etc.).
- **Component isolation**: Web Component styles live entirely in Shadow DOM `<style>` blocks,
  using the same design tokens via CSS custom properties.
- **BEM-like structure**: Not strict BEM, but scoped component classes
  (`app-sidebar`, `modal-h`, `modal-b`, `modal-f`).

---

## Cross-Language Patterns

### UUID Format

Lowercase hyphenated format everywhere. Test fixtures use deterministic
pattern UUIDs for readability:

```
aaaaaaaa-1111-2222-3333-444444444444  (default tenant)
bbbbbbbb-1111-2222-3333-444444444444  (meeting)
eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001 (E2E meeting)
```

### API Error Codes

Snake_case string codes: `'not_found'`, `'invalid_request'`, `'meeting_not_found'`,
`'no_quorum'`, `'business_error'`, `'internal_error'`.

### Language in Code

PHP code: English comments and identifiers throughout.
JS code: English for technical logic; French for user-facing labels, UI strings,
and domain terminology (the application's primary language is French).

---

*Convention analysis: 2026-03-16*
