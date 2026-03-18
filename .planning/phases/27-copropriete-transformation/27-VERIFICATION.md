---
phase: 27-copropriete-transformation
verified: 2026-03-18T16:00:00Z
status: passed
score: 7/7 must-haves verified
re_verification: false
---

# Phase 27: Copropriete Transformation — Verification Report

**Phase Goal:** The application uses generic AG vocabulary throughout the UI — no copropriete-specific language visible to users — while all weighted-vote calculations remain functionally identical
**Verified:** 2026-03-18
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths (from ROADMAP Success Criteria)

| #   | Truth | Status | Evidence |
| --- | ----- | ------ | -------- |
| 1   | Zero occurrences of "copropriete", "tantiemes", "lot", "milliemes", "cle de repartition" in user-facing rendered HTML | VERIFIED | Full grep across public/ HTML, JS, CSS, SQL, MD returns 0 matches (excluding preserved backend aliases and LOT- data values) |
| 2   | Lot field absent from wizard member input form | VERIFIED | No `m.lot`, `member.lot`, lot prompt, or lot CSV column in wizard.js; no `.member-lot` CSS class in wizard.css |
| 3   | "Cle de repartition" absent from settings page; no broken stub JS | VERIFIED | No `distributionKeysBody`, `distributionKeysTable`, `btnAddKey`, `openKeyModal`, `initDistributionKeys` in public/; Cles de repartition card removed from settings.htmx.html and admin.htmx.html |
| 4   | PHPUnit test asserts POUR:3 CONTRE:1 (not 1:1) with voting_power 3 and 1 — passes | VERIFIED | tests/Unit/WeightedVoteRegressionTest.php exists, 3 tests, 6 assertions, all pass (PHPUnit output: OK (3 tests, 6 assertions)) |

### Additional Must-Haves (from PLAN-01 frontmatter)

| #   | Truth | Status | Evidence |
| --- | ----- | ------ | -------- |
| 5   | select option value='tantiemes' preserved; display text changed to 'Par poids de vote' | VERIFIED | settings.htmx.html line 104: `<option value="tantiemes">Par poids de vote</option>` |
| 6   | voting_power column, BallotsService, ImportService tantieme alias untouched | VERIFIED | BallotsService.php uses `$member['voting_power']`; ImportService.php line 237 retains 'tantiemes'/'tantièmes' CSV aliases |
| 7   | Full PHPUnit suite passes with no regressions | VERIFIED | 2865 tests, 6014 assertions, 0 failures, 14 pre-existing skips |

**Score: 7/7 truths verified**

---

## Required Artifacts

| Artifact | Expected | Status | Details |
| -------- | -------- | ------ | ------- |
| `tests/Unit/WeightedVoteRegressionTest.php` | Weighted vote regression test | VERIFIED | 77 lines, 3 test methods, 6 assertions; exercises VoteEngine::computeDecision with (3:1), (4:0), (1:1) scenarios |
| `public/assets/js/pages/wizard.js` | Wizard without lot field | VERIFIED | No `m.lot`, no lot prompt, no lot CSV column |
| `public/assets/css/wizard.css` | No .member-lot class | VERIFIED | grep returns no match |
| `public/assets/js/pages/settings.js` | Settings without openKeyModal / initDistributionKeys | VERIFIED | Both functions and call site removed; grep returns no match |
| `public/settings.htmx.html` | Settings without Cles de repartition section | VERIFIED | Section removed; no distributionKeys tokens remain |
| `public/admin.htmx.html` | Admin without distribution-keys tab and panel | VERIFIED | Tab button and panel removed; no distributionKeys tokens remain |
| `public/assets/js/core/shell.js` | Sidebar subtitle updated | VERIFIED | Line 683: `sub: 'Annuaire des membres'` |
| `public/help.htmx.html` | Generic vocabulary | VERIFIED | Zero copropri matches; "tantiemes" appears only in parenthetical example `(parts sociales, tantièmes, etc.)` — acceptable per plan |
| `public/index.html` | Feature label updated | VERIFIED | Line 291: `<h4>Pondération des voix</h4>` |
| `app/Repository/AggregateReportRepository.php` | Comment updated | VERIFIED | Line 99: "evolution des poids de vote" |
| `database/seeds/06_test_weighted.sql` | Generic comment | VERIFIED | No copropri/tantieme in comments |
| `database/seeds/08_demo_az.sql` | Generic comments | VERIFIED | No tantieme in comments |
| `database/seeds/03_demo.sql` | Comments and motion descriptions cleaned | VERIFIED | No copropri in comments or motion descriptions; LOT-xxx data values preserved |
| `docs/FAQ.md` | Generic vocabulary | VERIFIED | grep returns no copropri matches |
| `docs/GUIDE_FONCTIONNEL.md` | Generic vocabulary | VERIFIED | grep returns no copropri matches |
| `docs/directive-projet.md` | Generic vocabulary | VERIFIED | grep returns no copropri matches |
| `public/wizard.htmx.html` | Generic vocabulary (auto-fix) | VERIFIED | grep returns no copropri matches |
| `public/postsession.htmx.html` | Generic vocabulary (auto-fix) | VERIFIED | grep returns no copropri matches |
| `tests/Integration/WorkflowValidationTest.php` | Test fixture description updated | VERIFIED | grep returns no copropri matches |

---

## Key Link Verification

| From | To | Via | Status | Details |
| ---- | -- | --- | ------ | ------- |
| `tests/Unit/WeightedVoteRegressionTest.php` | `app/Services/VoteEngine.php` (via `app/Services/BallotsService.php`) | PHPUnit test exercises weighted vote tally | WIRED | Test imports AgVote\Service\VoteEngine and calls computeDecision with weighted inputs; all 3 tests pass |
| `public/settings.htmx.html` | `public/assets/js/pages/settings.js` | HTML references JS functions — removing both in sync | VERIFIED CLEAN | Neither `btnAddKey` nor `distributionKeysBody` appear in either file; removal is in sync |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| ----------- | ----------- | ----------- | ------ | -------- |
| CPR-01 | 27-01, 27-02 | UI label rename — remove "copropriété", "tantièmes", "lot" vocabulary from all user-facing strings | SATISFIED | Full codebase grep returns 0 copropri matches in user-facing files; help.htmx.html parenthetical "(tantièmes, etc.)" is a generic example, not a label |
| CPR-02 | 27-01 | Remove lot field from wizard member input form (dead code — not in DB schema) | SATISFIED | No lot prompt, no m.lot display, no lot CSV column; .member-lot CSS class removed |
| CPR-03 | 27-01 | Remove openKeyModal / "Clé de répartition" stub from settings.js (no API endpoint backs it) | SATISFIED | openKeyModal, initDistributionKeys removed from settings.js; Cles de repartition sections removed from settings.htmx.html and admin.htmx.html |
| CPR-04 | 27-01, 27-02 | Preserve voting_power column, BallotsService weight calculations, and tantième CSV import alias unchanged | SATISFIED | BallotsService.php line 104 uses `voting_power`; ImportService.php line 237 retains tantieme/tantièmes CSV aliases; VoteEngine untouched |
| CPR-05 | 27-01 | PHPUnit regression test for weighted vote tally correctness | SATISFIED | WeightedVoteRegressionTest.php exists, passes 3 tests (3:1 majority, 4:0 unanimous, 1:1 tie not adopted at 0.501 threshold) |

**All 5 requirements satisfied. No orphaned requirements.**

Note: REQUIREMENTS.md traceability table shows CPR-01 through CPR-05 mapped to Phase 27 — this matches the requirements claimed in both plans.

---

## Anti-Patterns Found

| File | Pattern | Severity | Impact |
| ---- | ------- | -------- | ------ |
| None | — | — | — |

No anti-patterns detected. No TODO/FIXME/placeholder comments, no empty implementations, no stub handlers found in modified files.

---

## Human Verification Required

### 1. Wizard member form — no lot prompt at runtime

**Test:** Open the wizard, navigate to the Members step. Add a member manually. Confirm no "Numéro de lot" prompt appears.
**Expected:** Only the name prompt and "Poids de vote" prompt appear.
**Why human:** DOM prompt() dialogs and wizard step rendering cannot be verified by static grep.

### 2. Settings page — no broken JS on load

**Test:** Load the settings page. Open browser console. Confirm no JS errors referencing openKeyModal, initDistributionKeys, or distribution-keys.
**Expected:** Page loads cleanly, settings tabs work, no console errors.
**Why human:** JavaScript runtime errors from removed function references can only be confirmed in a live browser.

### 3. Admin page — distribution-keys tab absent from UI

**Test:** Load the admin page. Confirm no "Clés de répartition" tab is visible in the settings tab row.
**Expected:** Tab list shows only the remaining settings tabs; no "Clés de répartition" tab.
**Why human:** Tab visibility in rendered HTML depends on browser rendering and CSS visibility.

---

## Gaps Summary

No gaps. All automated checks passed:

- PHPUnit weighted-vote regression test: 3 tests, 6 assertions, 0 failures
- Full PHPUnit suite: 2865 tests, 0 failures, 14 pre-existing skips
- Vocabulary audit across public/: 0 copropri matches
- Dead code audit: 0 openKeyModal/initDistributionKeys/member-lot/distributionKeys* matches in public/
- Preserved values: `value="tantiemes"` confirmed in settings.htmx.html; tantieme aliases confirmed in ImportService.php
- Seed SQL and docs: 0 copropri/tantieme matches outside preserved backend locations
- All 5 CPR requirements satisfied

The phase goal is achieved. Generic AG vocabulary is in force throughout the application; no copropriete-specific language is visible to users; weighted-vote calculation logic is functionally identical and covered by a passing regression test.

---

_Verified: 2026-03-18T16:00:00Z_
_Verifier: Claude (gsd-verifier)_
