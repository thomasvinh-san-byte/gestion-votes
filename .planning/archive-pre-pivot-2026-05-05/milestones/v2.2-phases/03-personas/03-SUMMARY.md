---
phase: 3
phase_name: Personas (Role Markers + Isolation)
milestone: v2.2
verdict: implemented
date: 2026-04-29
pr: 256
---

# Phase 3 SUMMARY — Personas

L'utilisateur connecté voit instantanément quel rôle il a dans l'app. Plus de doute.

| Req | Verdict | Approche |
|---|---|---|
| DESIGN-P01 | ✓ | `body[data-persona]::before` : bande 3px fixed top, colorée par `var(--role-X)`. transition 0.2s + respect prefers-reduced-motion. |
| DESIGN-P02 | ✓ | `.persona-badge` : pill colorée + dot blanc + label UPPERCASE. Inserted dans `partials/sidebar.html` au-dessus du footer. Masqué pour `data-persona="guest"`. |
| DESIGN-P03 | ✓ | `PageController::serve` injecte `<body data-persona="X">` via regex à partir de `AuthMiddleware::getCurrentRole()`. Mapping DB role → persona token (admin/operator/president/auditor/voter/public/guest). Label français via `personaLabel()`. |
| DESIGN-P04 | ✓ | `tests/Security/PersonaIsolationTest.php` (8 cas) : Reflection sur PageController, vérification des labels FR, des `data-requires-role` admin, présence des 6 tokens CSS, règles bande+badge. |

## Tests

`vendor/bin/phpunit --testsuite Security --no-coverage` → **22/22 verts** (11 hardening v2.1 + 8 personas + 3 lexique).

## Commits
- `feat(v2.2 phase 3): personas — bande 3px + badge sidebar + isolation tests`
