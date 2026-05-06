---
phase: 16
status: passed
verified: 2026-04-09
accepted: 2026-04-09
acceptance_note: "User accepted Option A — contrast DEFERRED per D-04 methodology. Phase 16 closed; contrast remediation scheduled as future token-level phase. Partial WCAG 2.1 AA conformance declared in v1.3-A11Y-REPORT.md section 6."
score: 6 VERIFIED / 1 PARTIAL (contrast DEFERRED per D-04, accepted by user)
re_verification:
  previous_status: human_needed
  previous_score: 5 VERIFIED / 1 PARTIAL / 1 UNCERTAIN
  gaps_closed:
    - "Keyboard-nav runtime validated: 6/6 green in Docker chromium (16.9s), report §4 + §6 updated"
  gaps_remaining:
    - "Contrast DEFERRED — still requires product/a11y owner acceptance of partial WCAG AA conformance"
  regressions: []
human_verification:
  - test: "Accept contrast DEFERRED per CONTEXT.md D-04 methodology split as satisfying phase 16 goal"
    expected: "Product/a11y owner signs off that WCAG AA partial conformance (structural CONFORME + keyboard CONFORME + contrast NON-CONFORME-tracked) meets milestone v1.3 goal, OR schedules phase 16-bis for token remediation before v1.3 ship"
    why_human: "D-04/D-05/D-06 in CONTEXT.md explicitly pre-declared the methodology: structural runner disables color-contrast to avoid CI false positives, and a dedicated contrast runner produces JSON for documentation. This is methodology, not waiver. But the phase goal sentence reads 'aucune violation critical/serious' and axe classifies color-contrast as 'serious' — 316 tracked-not-waived nodes remain. The delivered phase matches the CONTEXT contract exactly; the remaining question is whether that contract satisfies the ROADMAP goal sentence. That is a product/legal acceptance call, not a programmatic check."
---

# Phase 16: Accessibility Deep Audit — Verification Report

**Phase Goal (ROADMAP):** Aucune violation a11y critical/serious sur les 21 pages, conformance WCAG 2.1 AA
**Verified:** 2026-04-09
**Mode:** Re-verification after gap closure (initial run 2026-04-09)
**Verdict:** HUMAN VERIFICATION NEEDED (1 item — down from 2)

---

## Re-Verification Summary

| Previous gap | Status | Evidence |
| --- | --- | --- |
| keyboard-nav.spec.js runtime validation (was UNCERTAIN) | **CLOSED — VERIFIED** | Runtime 6/6 green in Docker chromium (16.9s). Four real a11y regressions discovered at first run and fixed: login autofocus (`d5557e12`), auth-banner ordering (`e5c4d1c5`), overlay `[hidden]` CSS (`def35a99`, `7e07ea57`), ag-modal shadow DOM focus trap (`ef0fc529`). Documentation: `f7f166ac` (report §4) + `29ccade6` (conformance table §6). |
| Contrast DEFERRED acceptable? (was PARTIAL/needs human) | **STILL HUMAN** | No new evidence — still a product acceptance call. Strengthened context: D-04/D-05/D-06 in CONTEXT.md are pre-execution methodology decisions, delivered phase matches contract exactly. |

**Delta:** 1 gap resolved programmatically, 1 gap remains requiring human acceptance.

---

## Observable Truths (Goal-Backward)

| #   | Truth                                                                       | Status       | Evidence                                                                                                                  |
| --- | --------------------------------------------------------------------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------- |
| 1   | axe-core scan actually executed on all 22 pages (21 HTMX + login)           | VERIFIED     | `tests/e2e/specs/accessibility.spec.js` PAGES array lines 65-88 lists 22 entries; loop at 91-99 runs axe per page         |
| 2   | Structural critical/serious violations all fixed                            | VERIFIED     | 16-02-SUMMARY + report §2: 48 nodes on 5 rules → 0. Commit `bcceb1f0` fixes validated in `/tmp/16-02-axe-final2.txt` 26/26 |
| 3   | WCAG 2.1 AA documented in `.planning/v1.3-A11Y-REPORT.md`                   | VERIFIED     | File exists, 389 lines, 7 D-14 sections all present with real data (updated §4 + §6)                                    |
| 4   | Playwright a11y integrated on critical-path specs                           | VERIFIED     | `accessibility.spec.js` (22 pages), `contrast-audit.spec.js` (env-gated), `keyboard-nav.spec.js` (6 tests) all exist      |
| 5   | No critical/serious violations remain across the 21 pages (goal literal)    | PARTIAL      | Structural axe: 22/22 PASS (0 critical, 0 serious). Color-contrast (axe serious): 316 nodes DEFERRED per D-04, tracked not waived |
| 6   | Contrast audit runnable and data captured                                   | VERIFIED     | `.planning/v1.3-CONTRAST-AUDIT.json` exists (3771 lines), generated 2026-04-09T09:26Z, 22 pages entries                  |
| 7   | Keyboard-nav spec runtime-validated                                         | **VERIFIED** | **6/6 green in Docker chromium, 16.9s. Four real regressions discovered and fixed during gap-closure. Report §4 + §6 updated.** |

**Score:** 6 VERIFIED / 1 PARTIAL (contrast DEFERRED — methodology pre-declared, product acceptance pending)

---

## Success Criteria (from ROADMAP)

### SC-1: axe-core scan executed on the 21 application pages (report produced) — VERIFIED
Unchanged from initial verification. 22/22 GREEN on structural matrix.

### SC-2: All critical + serious violations fixed or explicitly justified with waiver — VERIFIED for structural, DEFERRED for contrast
**Structural matrix (color-contrast disabled per D-04):**
- 48 nodes / 5 rule-ids → 0 (all fixed, no waivers)
- 1 structural waiver: projection skip-link (justified, expires 2026-10-09)

**Color-contrast (separate runner, not disabled):**
- 316 nodes across 22 pages, 42 unique `(fg,bg)` pairs, worst ratio 1.83 on wizard step numbers
- Report §3 explicitly marks these as **DÉFÉRÉ** (not waived): root cause in shared design tokens requires dedicated remediation phase
- Report §6 declares partial conformance: "NON CONFORME (déféré)" for contrast

**Methodology defense (strengthened by re-read of CONTEXT.md):**
- **D-04** (pre-execution): `axeAudit.js` disables `color-contrast` on structural runner. Pre-declared reason: tokens are shared and theme-dependent, prevents CI false positives.
- **D-05** (pre-execution): Dedicated contrast runner via `contrast-audit.spec.js`, manual / one-shot, produces JSON.
- **D-06** (pre-execution): Results documented in report with measured ratios.

The phase delivered **exactly** what CONTEXT promised: structural=0 + dedicated contrast runner + JSON baseline + documented partial conformance. The remaining question — "does pre-declared methodology partition satisfy the ROADMAP goal sentence?" — is a product acceptance call, not a programmatic gap.

### SC-3: WCAG 2.1 AA conformance documented in .planning/v1.3-A11Y-REPORT.md — VERIFIED
All 7 D-14 sections present, §4 now reflects 6/6 runtime green with root-cause analysis of the 4 discovered regressions, §6 conformance table updated: keyboard dimension now **CONFORME** (was "CONFORME au niveau spec"). No placeholders, French, no copropriété/syndic.

### SC-4: Playwright a11y tests integrate axe-core on each critical-path spec — VERIFIED
- `accessibility.spec.js` — 22-page axe matrix GREEN
- `contrast-audit.spec.js` — env-gated, produced JSON
- `keyboard-nav.spec.js` — **6/6 runtime green** (previously UNCERTAIN, now VERIFIED)

---

## Required Artifacts

| Artifact                                                 | Status     | Notes                                                |
| -------------------------------------------------------- | ---------- | ---------------------------------------------------- |
| `tests/e2e/specs/accessibility.spec.js`                  | VERIFIED   | 22-page parametrized matrix, 26/26 GREEN             |
| `tests/e2e/specs/keyboard-nav.spec.js`                   | VERIFIED   | 214 lines, 6 tests, **runtime 6/6 green**            |
| `tests/e2e/specs/contrast-audit.spec.js`                 | VERIFIED   | env-gated, writes JSON, 22 pages                     |
| `.planning/v1.3-CONTRAST-AUDIT.json`                     | VERIFIED   | 3771 lines, valid JSON, generated 2026-04-09         |
| `.planning/v1.3-A11Y-REPORT.md`                          | VERIFIED   | 389 lines, 7 sections, §4+§6 updated post-gap-closure |
| `tests/e2e/helpers/axeAudit.js` (extraDisabledRules opt) | VERIFIED   | Option plumbed, used by spec                         |
| `docker-compose.override.yml`                            | VERIFIED   | Bind-mount `public/`, committed `2ae5f8d2`           |

Regression check on previously-verified artifacts: all pass (sanity existence + basic substance).

---

## Key Links

Unchanged from initial verification — all WIRED.

| From                        | To                           | Status |
| --------------------------- | ---------------------------- | ------ |
| accessibility.spec.js       | axeAudit.js                  | WIRED  |
| accessibility.spec.js loop  | All 22 pages                 | WIRED  |
| contrast-audit.spec.js      | v1.3-CONTRAST-AUDIT.json     | WIRED  |
| v1.3-A11Y-REPORT.md §3      | v1.3-CONTRAST-AUDIT.json     | WIRED  |
| keyboard-nav.spec.js        | ag-modal custom element      | WIRED  |
| keyboard-nav.spec.js runtime| Docker chromium 6/6 green    | **WIRED (new)** |
| 16-02 fixes                 | Axe baseline re-run GREEN    | WIRED  |

---

## Requirements Coverage

| Req        | Status    | Evidence                                                                       |
| ---------- | --------- | ------------------------------------------------------------------------------ |
| A11Y-01    | SATISFIED | 22/22 structural GREEN                                                         |
| A11Y-02    | SATISFIED | 48 structural nodes fixed + 4 runtime regressions discovered and fixed         |
| A11Y-03    | SATISFIED | Report §4/§6 declares honest partial conformance (structural+keyboard CONFORME, contrast NON-CONFORME-tracked) |

---

## Anti-Patterns Scan

No blockers. Report updates contain real data (ratios, node counts, commit hashes). No placeholders. Gap-closure commits each carry meaningful fix messages.

---

## Human Verification Item (1 remaining)

### Contrast DEFERRED acceptable for v1.3 ship?

**Context:** Phase goal = "aucune violation critical/serious sur les 21 pages". axe-core impact taxonomy classifies `color-contrast` as `serious`. Report §6 declares NON-CONFORME on contrast dimension with 316 tracked-but-unfixed nodes across all 22 pages. Worst ratio 1.83 (wizard step numbers).

**Stronger argument for ACCEPT (after re-reading CONTEXT.md D-04/D-05/D-06):**
- D-04/D-05/D-06 are **pre-execution** decisions recorded in CONTEXT.md before plans 16-01 through 16-05 were written
- The partition (structural runner disables color-contrast + dedicated contrast runner produces JSON) is methodology, not evasion
- The phase executed the CONTEXT contract **exactly** — no scope creep, no hidden waivers, no surprises
- Report is honest: declares partial conformance, documents 6 token root causes, lists remediation priorities
- Contrast baseline JSON is re-runnable — post-remediation delta is measurable
- No waiver applied (tracked as tech debt, not swept under rug)

**Argument for BLOCKING:**
- Axe severity terminology maps directly to the goal sentence
- Low-vision users hit real barriers on muted text, KPI labels, wizard step numbers
- "Partial conformance" is strictly weaker than the roadmap promise

**Decision the user must make:**
- **Option A — ACCEPT DEFERRED:** Mark phase 16 complete, ship v1.3 with partial conformance, schedule 16-bis (token remediation) for v1.4 or insert before v1.3 ship at leisure.
- **Option B — BLOCK:** Schedule 16-bis immediately to fix the 6 token pairs before v1.3 ships. Token fix is architectural (6 values in design tokens), estimated small — but needs design review because it touches the warm-neutral identity.

---

## Gaps Summary

- **Programmatic gaps:** 0 remaining. The keyboard-nav runtime uncertainty was resolved by actual execution (6/6 green) plus discovery and fix of 4 real regressions during gap-closure.
- **Product acceptance gaps:** 1 — contrast DEFERRED partition must be accepted (or rejected) by product/a11y owner.

Phase deliverables are complete and aligned with CONTEXT.md. Code and docs are consistent. The only remaining question is whether the pre-declared methodology partition satisfies the ROADMAP goal sentence. That is a sign-off question, not a code question.

---

## HUMAN VERIFICATION NEEDED

Down to 1 item from 2:
1. ~~Run keyboard-nav.spec.js in Docker~~ **DONE — 6/6 green, regressions fixed, report updated.**
2. Accept contrast DEFERRED per CONTEXT.md D-04 methodology split as satisfying phase 16 goal — **OR** schedule 16-bis before v1.3 ship.

If the user accepts DEFERRED → update this file's `status:` to `passed` and mark phase complete.
If the user requires contrast fix → create phase 16-bis plan via `/gsd:plan-phase --gaps`.

---

*Re-verified: 2026-04-09*
*Verifier: Claude (gsd-verifier, Opus 4.6)*
*Previous run: 2026-04-09 (same day — initial → re-verification after 7 gap-closure commits)*
