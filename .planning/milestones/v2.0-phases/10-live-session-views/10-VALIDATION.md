---
phase: 10
slug: live-session-views
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (backend), manual browser testing (frontend CSS/JS) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `vendor/bin/phpunit --testsuite unit` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Open page in browser, verify no CSS regressions
- **After every plan wave:** Full visual check: public page (dark+light), voter page (mobile+tablet+desktop)
- **Before `/gsd:verify-work`:** Both pages pixel-checked against design tokens
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-01-01 | 01 | 1 | DISP-01 | manual | Visual browser check — design tokens applied | N/A | ⬜ pending |
| 10-01-02 | 01 | 1 | DISP-02 | manual | Load public.htmx.html — horizontal bars render | N/A | ⬜ pending |
| 10-02-01 | 02 | 1 | VOTE-01 | manual | DevTools 768px viewport — touch layout renders | N/A | ⬜ pending |
| 10-02-02 | 02 | 1 | VOTE-02 | manual | Mobile browser test — buttons respond to touch | N/A | ⬜ pending |
| 10-02-03 | 02 | 1 | VOTE-03 | manual | Browser network tab + operator panel — toggle calls API | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. This phase is a CSS/HTML restyle with one new UI element (presence toggle). Manual browser verification is the appropriate gate — no new test framework needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Room display uses design tokens, no hardcoded colors | DISP-01 | CSS visual check | Inspect public.css — all colors reference `var(--*)` tokens |
| Horizontal result bars render with live data | DISP-02 | Layout visual check | Load public.htmx.html, trigger vote — bars grow horizontally |
| Touch layout renders on tablet viewport | VOTE-01 | Responsive visual check | DevTools → 768px width, verify layout shifts |
| Vote buttons respond to touch/click | VOTE-02 | Interaction check | Click Pour/Contre/Abstention/Blanc — confirmation overlay appears |
| Present/absent toggle calls API and disables buttons | VOTE-03 | API + UI integration | Toggle absent → verify POST to /api/v1/attendance + buttons disabled |

---

## Validation Sign-Off

- [ ] All tasks have manual verification instructions
- [ ] Sampling continuity: visual check after each task commit
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
