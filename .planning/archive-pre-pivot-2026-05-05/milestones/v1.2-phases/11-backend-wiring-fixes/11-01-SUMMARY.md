---
phase: 11-backend-wiring-fixes
plan: "01"
subsystem: vote-engine
tags: [settings, vote-policy, quorum-policy, fallback-synthesis]
dependency_graph:
  requires: []
  provides:
    - settQuorumThreshold wired into QuorumEngine fallback path
    - settVoteMode wired into VoteEngine motion.secret override
    - settMajority wired into VoteEngine fallback vote policy
  affects:
    - app/Services/VoteEngine.php
    - app/Services/QuorumEngine.php
tech_stack:
  added: []
  patterns:
    - Constructor-injected SettingsRepository with nullable default
    - Fallback policy synthesis from tenant settings (synthesized policy array, no DB row)
    - Explicit policies always win over settings fallback
key_files:
  created:
    - tests/Unit/QuorumEngineSettingsTest.php
    - tests/Unit/VoteEngineSettingsTest.php
  modified:
    - app/Services/QuorumEngine.php
    - app/Services/VoteEngine.php
    - tests/Unit/QuorumEngineTest.php
    - tests/Unit/VoteEngineTest.php
decisions:
  - "Lazy-loading avoided: test builders updated to inject mock SettingsRepository returning null, preserving noPolicy() behavior in existing tests"
  - "computeForMotion() NOT touched for settings fallback — it has its own fallback chain and touching it risks regressions"
  - "settVoteMode override applied unconditionally (not gated on explicit policy presence) per plan spec"
metrics:
  duration_minutes: 35
  completed: "2026-04-08"
  tasks_completed: 2
  tasks_total: 2
  files_changed: 6
requirements: [FIX-01]
---

# Phase 11 Plan 01: Vote Settings Wiring Summary

Wire three DEAD vote settings (`settVoteMode`, `settQuorumThreshold`, `settMajority`) into VoteEngine and QuorumEngine via fallback policy synthesis — changing a setting now changes the calculation result.

## What Was Done

### Task 1: QuorumEngine settQuorumThreshold fallback

`QuorumEngine::computeForMeeting()` previously returned `noPolicy()` when no explicit `quorum_policy_id` was set on the meeting. It now calls `resolveFallbackQuorumPolicy($tenantId)` first. That method reads `settQuorumThreshold` from `tenant_settings` and synthesizes an in-memory policy array with `threshold = pct / 100.0` and `denominator = eligible_members`. If the setting is absent or out of range, `null` is returned and `noPolicy()` is called as before.

Code path: `computeForMeeting() → resolveFallbackQuorumPolicy() → settingsRepo->get($tid, 'settQuorumThreshold')`

### Task 2: VoteEngine settMajority + settVoteMode fallback

`VoteEngine::computeMotionResult()` previously left `$votePolicy = null` when no explicit vote policy was linked to the motion or meeting. It now calls `resolveFallbackVotePolicy($tenantId)` after the existing policy-resolution block. That method reads `settMajority` and returns a synthetic policy with `base = expressed` and the appropriate threshold (0.5 for `simple`/`absolute`, 2/3 for `two_thirds`, 0.75 for `three_quarters`).

Additionally, `settVoteMode` is now read and used to override `motion.secret` in the result: `secret` → `true`, `public` → `false`. This override runs regardless of whether an explicit policy is set.

Code paths:
- `computeMotionResult() → resolveFallbackVotePolicy() → settingsRepo->get($tid, 'settMajority')`
- `computeMotionResult() → settingsRepo->get($tid, 'settVoteMode') → $motionSecret override`

## Tests

9 new PHPUnit tests across two new test files:

| File | Tests | What Is Proved |
|---|---|---|
| `QuorumEngineSettingsTest.php` | 4 | settQuorumThreshold 60 → threshold 0.60; 75 → threshold 0.75; no setting → noPolicy; explicit policy wins |
| `VoteEngineSettingsTest.php` | 6 | two_thirds → adopted; three_quarters → rejected; simple → 0.5; secret → motion.secret=true; public → motion.secret=false; explicit policy wins over majority setting |

All 136 tests (9 new + 127 pre-existing) pass in the combined run.

## Decisions Made

1. **Test builder update over lazy-loading**: Rather than adding lazy-loading complexity to avoid DB calls, the `buildEngine()` / `buildVoteEngine()` helpers in existing test files were updated to inject a mock `SettingsRepository` returning `null`. This is cleaner and keeps the existing tests green with no behavioral change.

2. **computeForMotion() not touched**: The plan explicitly prohibits adding settings fallback to `computeForMotion()` to avoid regressions in the existing motion-level policy fallback chain.

3. **settVoteMode unconditional override**: The motion `secret` flag is overridden from the setting regardless of whether an explicit vote policy is present, matching the plan specification.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

All key files exist. Both task commits verified:
- `13b785d0` — QuorumEngine settings wiring + test
- `00a0fb3b` — VoteEngine settings wiring + test
