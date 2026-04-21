---
phase: 4
slug: clarte-et-jargon
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 + Playwright E2E |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Full suite command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Estimated runtime** | ~30 seconds |

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 4-01-01 | 01 | 1 | CLAR-01 | grep | `! grep -q 'quorum' public/public.htmx.html` | ⬜ pending |
| 4-01-02 | 01 | 1 | CLAR-02 | grep | `grep -q 'ag-tooltip' public/operator.htmx.html` | ⬜ pending |
| 4-02-01 | 02 | 1 | CLAR-03 | grep | `grep -q 'confirmCheckbox' public/validate.htmx.html` | ⬜ pending |
| 4-02-02 | 02 | 1 | CLAR-04 | grep | `grep -q 'export-desc' public/archives.htmx.html` | ⬜ pending |

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| No technical terms visible to voter | CLAR-01 | Content audit | Login as voter, check all visible pages |
| Tooltip shows on hover | CLAR-02 | Interactive behavior | Hover over technical term on admin page |
| Checkbox confirmation works | CLAR-03 | Interactive behavior | Go to validate page, check checkbox, click Confirmer |
| Export descriptions visible | CLAR-04 | Visual | Open archives export modal, verify descriptions |

---

## Validation Sign-Off

- [ ] All tasks have automated verify
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
