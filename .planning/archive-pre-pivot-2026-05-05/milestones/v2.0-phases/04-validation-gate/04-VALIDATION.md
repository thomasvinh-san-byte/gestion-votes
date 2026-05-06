---
phase: 4
slug: validation-gate
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-29
---

# Phase 4 — Validation Strategy

> Verification gate. No new code; validates the full v2.0 milestone.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright + node --check + php -l |
| **Quick run command** | `node --check public/assets/js/pages/{operator-exec,operator-realtime,operator-tabs}.js` |
| **PHP syntax** | `find /home/user/gestion_votes_php -name '*.php' -newer .git/refs/heads/main` (none expected) |
| **Playwright** | DEFERRED — blocked locally by missing `libatk-1.0.so.0`, runs in CI |
| **Estimated runtime** | ~10 seconds (static checks) |

---

## Sampling Rate

- **After every task commit:** Run static checks (sub-second feedback)
- **Final:** Manual audit consolidation in `04-AUDIT.md`

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 4-01-01 | 01 | 1 | All v2.0 | static | `node --check` on modified JS files | pending |
| 4-01-02 | 01 | 1 | PHP boundary | static | `php -l` on changed PHP (zero expected) | pending |
| 4-01-03 | 01 | 1 | Manual verif consolidation | doc | grep + review | pending |

---

## Manual-Only Verifications

| Behavior | Source Phase | Why Manual | Test Instructions |
|----------|--------------|------------|-------------------|
| Phase 1 alert pulse animation | CHECK-05 | CSS timing | Force quorum below threshold, verify red pulse on alert row |
| Phase 1 SSE disconnect banner | CHECK-03 | Network interruption | Disconnect SSE, verify "Connexion perdue" banner |
| Phase 2 5-zone layout @1080p | FOCUS-01 | Layout perception | Toggle focus mode at 1920x1080, count visible zones (must be 5) |
| Phase 2 action buttons no scroll | FOCUS-02 | Viewport-dependent | Verify Proclamer/Fermer/Suivante visible without scrolling |
| Phase 2 toggle persists | FOCUS-03 | Multi-step interaction | Activate focus, switch mode, switch back — focus restored |
| Phase 3 counter tween | ANIM-01 | Timing | Cast vote, verify counter visibly increments over 400ms |
| Phase 3 bar slide | ANIM-02 | Timing | Cast vote, verify bar width slides smoothly |
| Phase 3 reduced motion | ANIM-03 | OS pref | Enable reduced-motion in OS, cast vote, verify instant update |

These are deferred to manual QA before release tag.

---

## Validation Sign-Off

- [x] All tasks have automated grep/syntax verify
- [x] Sampling continuity: every task has automated check
- [x] Wave 0 not required (existing infra)
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set

**Approval:** approved 2026-04-29
