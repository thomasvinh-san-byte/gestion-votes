---
phase: 16
slug: accessibility-deep-audit
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-09
---

# Phase 16 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright 1.59.1 + @axe-core/playwright 4.10.2 (Node 20) + PHPUnit 10.5 (PHP 8.4) |
| **Config file** | `tests/e2e/playwright.config.js` |
| **Quick run command** | `cd tests/e2e && timeout 120 npx playwright test specs/accessibility.spec.js --reporter=line` |
| **Full suite command** | `bin/test-e2e.sh accessibility.spec.js keyboard-nav.spec.js` |
| **Estimated runtime** | ~90s quick (paramétrisé 21 pages) · ~150s full (avec keyboard-nav) |

Contrast audit (one-shot, manuel):
- `cd tests/e2e && CONTRAST_AUDIT=1 npx playwright test specs/contrast-audit.spec.js`

PHP unit tests (si nécessaire pour SettingsController fix):
- `timeout 60 php vendor/bin/phpunit tests/Unit/Controller/SettingsControllerTest.php --no-coverage`

---

## Sampling Rate

- **After every task commit:** Run `timeout 120 npx playwright test specs/accessibility.spec.js --reporter=line` (ciblé)
- **After every plan wave:** Run full `bin/test-e2e.sh` sur specs a11y + keyboard-nav
- **Before `/gsd:verify-work`:** Full suite a11y green, contrast audit exécuté, rapport généré
- **Max feedback latency:** 120 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 16-01-01 | 01 | 0 | A11Y-02 | seed-commit | N/A (WIP déjà diagnostiqué) | ✅ | ⬜ pending |
| 16-01-02 | 01 | 1 | A11Y-01 | e2e | `npx playwright test specs/accessibility.spec.js -g "dashboard"` | ❌ W0 | ⬜ pending |
| 16-02-01 | 02 | 1 | A11Y-01 | e2e | `npx playwright test specs/accessibility.spec.js` (baseline run 21 pages) | ❌ W0 | ⬜ pending |
| 16-02-02 | 02 | 1 | A11Y-02 | e2e | `npx playwright test specs/accessibility.spec.js` (après fix par rule-id) | ❌ W0 | ⬜ pending |
| 16-03-01 | 03 | 2 | A11Y-03 | e2e | `npx playwright test specs/keyboard-nav.spec.js` | ❌ W0 | ⬜ pending |
| 16-04-01 | 04 | 2 | A11Y-03 | script | `CONTRAST_AUDIT=1 npx playwright test specs/contrast-audit.spec.js` | ❌ W0 | ⬜ pending |
| 16-05-01 | 05 | 3 | A11Y-03 | doc-gen | `test -f .planning/v1.3-A11Y-REPORT.md` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/e2e/specs/accessibility.spec.js` — paramétrisation via PAGES array (extend existing)
- [ ] `tests/e2e/helpers/axeAudit.js` — ajout param `extraDisabledRules`
- [ ] `tests/e2e/specs/keyboard-nav.spec.js` — nouveau fichier (skip-link, focus trap, Tab order)
- [ ] `tests/e2e/specs/contrast-audit.spec.js` — nouveau fichier (one-shot, gated par env var)
- [ ] `.planning/v1.3-A11Y-REPORT.md` — rapport final (généré en fin de phase)
- [ ] Commit des WIP seeds (D-08) comme tâche 16-01-01

*Existing infrastructure:* Playwright + axe-core déjà installés, 7 pages déjà couvertes, loginAs* helpers prêts, `ag-modal.js` avec focus trap existant, skip-link existant dans layout shells.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Flow critique login→vote→logout keyboard-only | A11Y-03 | Test keyboard navigation end-to-end avec vrai browser, focus-visible | Lancer login → Tab/Enter uniquement → voter sur une résolution → logout. Documenter dans rapport section keyboard. |
| Waivers justification | A11Y-02 | Décision humaine sur justification technique/métier | Chaque waiver relu manuellement lors de la review de v1.3-A11Y-REPORT.md |
| Contrast ratios interprétation | A11Y-03 | Les ratios bruts ne disent pas ce qui est acceptable (texte décoratif vs texte info) | Revue manuelle de la sortie `v1.3-CONTRAST-AUDIT.json` |
| Screen reader spot-check (VoiceOver ou NVDA) | A11Y-03 | Pas automatisable, échantillonnage qualitatif | Tester page publique de vote avec un screen reader, noter ressenti dans rapport |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 120s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
