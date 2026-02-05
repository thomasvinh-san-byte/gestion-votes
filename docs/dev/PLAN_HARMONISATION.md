# AG-VOTE Harmonization Plan

> Based on comprehensive audit - February 2026

## Overview

This plan addresses structural inconsistencies ("Frankenstein" patterns) identified during the full project audit. The goal is to achieve end-to-end design coherence.

---

## Phase 1: Backend Foundations (Critical)

### 1.1 PHP Namespaces Standardization

All PHP classes must use `AgVote\*` namespace with PSR-4 autoloading.

| File | Current | Target |
|------|---------|--------|
| `app/Core/Security/AuthMiddleware.php` | No namespace | `AgVote\Core\Security` |
| `app/Core/Security/CsrfMiddleware.php` | No namespace | `AgVote\Core\Security` |
| `app/Core/Security/RateLimiter.php` | No namespace | `AgVote\Core\Security` |
| `app/Core/Security/PermissionChecker.php` | No namespace | `AgVote\Core\Security` |
| `app/Core/Security/permissions.php` | No namespace | `AgVote\Core\Security\Permissions` (rename file) |
| `app/Core/Validation/InputValidator.php` | No namespace | `AgVote\Core\Validation` |
| `app/Core/Validation/Schemas/ValidationSchemas.php` | `App\Core\*` | `AgVote\Core\Validation\Schemas` |
| `app/Core/Security/SecurityHeaders.php` | `App\Core\*` | `AgVote\Core\Security` |

**Tasks:**
- [x] Add namespaces to all Security classes
- [x] Add namespace to InputValidator
- [x] Change `App\Core\*` to `AgVote\Core\*`
- [x] Rename `permissions.php` → `Permissions.php`
- [x] Update `composer.json` autoload section
- [x] Remove `require_once` from `bootstrap.php` (use autoload)
- [x] Run `composer dump-autoload`

### 1.2 Directory Rename

| Current | Target |
|---------|--------|
| `app/services/` | `app/Services/` |

**Tasks:**
- [x] Rename directory
- [x] Update `composer.json` PSR-4 mapping
- [x] Verify all imports work

### 1.3 Tenant Isolation Security Fix

**Critical:** Some repository methods lack tenant filtering.

| Method | Issue |
|--------|-------|
| `MemberRepository::findById()` | Missing `tenant_id` check |

**Tasks:**
- [x] Audit all `findById()` methods without tenant parameter
- [x] Add mandatory `tenant_id` to critical methods (added `findByIdForTenant()`)
- [ ] Add tests for tenant isolation (deferred to Phase 6)

### 1.4 Unified API Response Format

**Standard format:**
```json
{
  "ok": true|false,
  "data": { ... },
  "error": "error_code",
  "message": "Human readable message"
}
```

**Tasks:**
- [x] Document response format in API.md (already implemented via `json_ok()`)
- [x] Fix endpoints returning outside `data` wrapper (already correct)
- [x] Ensure all errors use ErrorDictionary (already implemented)

### 1.5 Database Scripts Update

**Tasks:**
- [x] Update `database/schema-master.sql` if schema changes needed (added clarification comments)
- [x] Review migrations for consistency
- [x] Ensure seeds use correct column names (documented `voting_power` vs `vote_weight` situation)

---

## Phase 2: Centralized Validation

### 2.1 InputValidator Enforcement

Replace inline validation with InputValidator schemas.

**Current (inconsistent):**
```php
// Pattern A: inline
if ($title === '') api_fail('missing_title', 422);

// Pattern B: InputValidator (rarely used)
InputValidator::schema()->string('title')->required();

// Pattern C: api_require_uuid helper
$id = api_require_uuid($in, 'id');
```

**Target:** Single pattern using InputValidator for all endpoints.

**Tasks:**
- [ ] Create validation schemas for all endpoints
- [ ] Replace inline validation with InputValidator
- [ ] Keep `api_require_uuid()` as convenience wrapper calling InputValidator

### 2.2 Unified Error Handling

**Tasks:**
- [ ] Create reusable transaction wrapper in `api.php`
- [ ] Add try/catch to all endpoints (currently 68/170)
- [ ] Use ErrorDictionary for all error messages
- [ ] Standardize HTTP status codes (400 vs 422 for validation)

---

## Phase 3: Frontend State Management

### 3.1 Centralize meeting_id

**Problem:** 8 different sources for `meeting_id`.

**Solution:** Create `MeetingContext` singleton.

```javascript
// New: public/assets/js/meeting-context.js
const MeetingContext = {
  _meetingId: null,

  get() { return this._meetingId; },
  set(id) { this._meetingId = id; localStorage.setItem('meeting_id', id); },
  init() { this._meetingId = new URLSearchParams(location.search).get('meeting_id') || localStorage.getItem('meeting_id'); }
};
```

**Tasks:**
- [ ] Create `MeetingContext` singleton
- [ ] Refactor `operator.js` to use MeetingContext
- [ ] Refactor `vote.js` to use MeetingContext
- [ ] Refactor `shell.js` to use MeetingContext
- [ ] Remove duplicate `getMeetingId()` functions

### 3.2 ES6 Module Conversion

| File | Current | Target |
|------|---------|--------|
| `vote.js` | IIFE | ES6 module |
| `shared.js` | IIFE | ES6 module |
| `admin.js` | IIFE | ES6 module |
| `operator.js` | IIFE | ES6 module |

**Tasks:**
- [ ] Convert each file to ES6 module with explicit exports
- [ ] Update HTML script tags to `type="module"`
- [ ] Ensure proper import/export chains

### 3.3 Unified Notification System

**Current:** Two systems coexist (`setNotif()` and `AgToast`).

**Target:** AgToast only.

**Tasks:**
- [ ] Remove `setNotif()` from `shared.js`
- [ ] Replace all `setNotif()` calls with `AgToast.show()`
- [ ] Ensure AgToast is globally available

### 3.4 WebSocket vs Polling Fix

**Problem:** Both run simultaneously, causing race conditions.

**Tasks:**
- [ ] Disable polling when WebSocket is connected
- [ ] Add fallback: enable polling only on WebSocket disconnect
- [ ] Document which events are broadcasted via WebSocket

---

## Phase 4: CSS/Design System Cleanup

### 4.1 Button Variants

**Remove legacy syntax:**
```css
/* REMOVE: */
.btn.primary { }
.btn.ghost { }
.btn.sm { }

/* KEEP: */
.btn-primary { }
.btn-ghost { }
.btn-sm { }
```

**Tasks:**
- [ ] Remove `.btn.{variant}` rules from CSS
- [ ] Update HTML using legacy syntax
- [ ] Document official variants

### 4.2 Typography Scale

**Remove legacy classes:**
```css
/* REMOVE: */
.tiny { }
.h1 { }
.h2 { }

/* KEEP: */
.text-xs { }
.text-sm { }
.text-base { }
/* ... */
.text-4xl { }
```

**Tasks:**
- [ ] Remove legacy typography classes
- [ ] Update HTML using legacy classes
- [ ] Document typography scale

---

## Phase 5: Conventions & Documentation

### 5.1 Linter Configurations

**Tasks:**
- [ ] Create `.eslintrc.json` with standard rules
- [ ] Create `.prettierrc.json` for formatting
- [ ] Create `.editorconfig` for editor consistency

### 5.2 Documentation

**Language:** English for all code and documentation.

**Tasks:**
- [ ] Add JSDoc to all JavaScript files
- [ ] Complete PHPDoc for under-documented files
- [ ] Create `CONTRIBUTING.md` with coding conventions
- [ ] Convert French comments to English

### 5.3 Language Harmonization

**Decision:** All code, comments, and documentation in English.

**Tasks:**
- [ ] Convert French comments to English (PHP)
- [ ] Convert French comments to English (JS)
- [ ] Keep French only for user-facing strings (UI labels, error messages)

---

## Phase 6: Tests (After Harmonization)

### 6.1 Coverage Improvement

**Current:** ~10% coverage
**Target:** 30%+ coverage

**Tasks:**
- [ ] Add tests for InputValidator
- [ ] Add tests for critical services (VoteEngine, QuorumEngine)
- [ ] Add tests for tenant isolation
- [ ] Add tests for AuthMiddleware

---

## Execution Order

```
Phase 1 (Backend Foundations)
    ↓
Phase 2 (Validation)
    ↓
Phase 3 (Frontend State)
    ↓
Phase 4 (CSS Cleanup)
    ↓
Phase 5 (Conventions)
    ↓
Phase 6 (Tests)
```

---

## Files Reference

### Critical Backend Files
- `/app/bootstrap.php` - Entry point, remove require_once
- `/app/api.php` - API helpers, add transaction wrapper
- `/app/Core/Security/*.php` - Add namespaces
- `/app/Core/Validation/*.php` - Add namespaces
- `/app/Repository/*.php` - Fix tenant isolation
- `/composer.json` - Update autoload

### Critical Frontend Files
- `/public/assets/js/meeting-context.js` - Create new
- `/public/assets/js/operator.js` - Refactor
- `/public/assets/js/vote.js` - Refactor
- `/public/assets/js/shared.js` - Refactor
- `/public/assets/js/admin.js` - Refactor

### Critical CSS Files
- `/public/assets/css/design-system.css` - Remove legacy
- `/public/assets/css/app.css` - Remove legacy

### Database Files
- `/database/schema-master.sql` - Review
- `/database/seeds/*.sql` - Fix column naming

---

## Success Criteria

- [ ] All PHP classes use `AgVote\*` namespace
- [ ] Zero `require_once` in bootstrap (PSR-4 autoload only)
- [ ] All API responses follow standard format
- [ ] Single source of truth for `meeting_id`
- [ ] All JS files are ES6 modules
- [ ] Single notification system (AgToast)
- [ ] No legacy CSS syntax
- [ ] All comments in English
- [ ] 30%+ test coverage
