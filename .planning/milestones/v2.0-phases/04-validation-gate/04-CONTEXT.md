---
phase: 4
slug: validation-gate
status: locked
created: 2026-04-29
---

# Phase 4 — Validation Gate: Decisions Locked

## Phase Goal

Toutes les fonctionnalites v2.0 sont verifiees sans regression sur le reste de l'application.

## Locked Decisions

### D-1 — Phase 4 is a verification gate, NOT a feature phase

No new code, no new dependencies, no DOM/CSS additions. Output is a regression audit document + automated checks + remediation of any issues found.

### D-2 — Playwright E2E remains BLOCKED locally

The dev container is missing `libatk-1.0.so.0`. This affects all phases of v2.0. Per CLAUDE.md test rules, we will NOT install system packages or modify the dev environment. Playwright validation is deferred to a future "infra fix" task or to CI runs (assumed to have the correct system libs).

Phase 4 will:
- Mark Playwright runs as PENDING (deferred to CI)
- Verify static checks that ARE runnable

### D-3 — PHP syntax check applies even with zero PHP changes

Per success criterion: "La syntaxe PHP de tous les fichiers modifies est valide". Phase 1, 2, 3 modified zero PHP files. We will verify this claim and document the empty result rather than skip the check.

### D-4 — Regression scope: read-only audit

Manual browser verification is documented in each phase's VALIDATION.md as "Manual-Only Verifications". Phase 4 consolidates these into a single AUDIT.md and explicitly lists items that require human or CI verification.

For automated regression detection, Phase 4 runs:
- `node --check` on every modified JS file
- `grep` audits to confirm no orphan references (functions called but not defined, IDs referenced but not in DOM, etc.)
- `git diff` review for unintended cross-cutting changes

### D-5 — Plan structure: single plan, single wave

Three tasks:
1. Static regression audit (JS syntax, orphan references, cross-cutting changes)
2. PHP boundary check (confirm zero PHP changes; if any, run `php -l`)
3. Consolidate manual verification checklist into `04-AUDIT.md`

## Out of Scope

- Installing libatk to unblock Playwright (infra concern, separate ticket)
- Running the full E2E suite (deferred to CI)
- New features
- Refactoring
