---
phase: 3
slug: animations-vote
status: locked
created: 2026-04-29
---

# Phase 3 — Animations Vote: Decisions Locked

## Phase Goal

Les compteurs et barres de vote s'animent fluidement a chaque nouveau vote recu via SSE, avec respect de prefers-reduced-motion.

## Locked Decisions

### D-1 — Counter tween: vanilla RAF, NOT Anime.js helper

Researchers diverged: RESEARCH suggested reusing `animateKpiValue()` (Anime.js); UI-SPEC suggested vanilla RAF integer ticking.

**Locked:** Vanilla RAF. New helper `animateVoteCounter(el, from, to, opts)` in `operator-exec.js`.

**Why:**
- Avoids Anime.js dependency creep into vote-card hot path (Anime.js loaded only for KPI strip currently — keep its scope contained)
- 400ms ease-out integer ticking is trivial in vanilla JS (~15 lines)
- RAF gives precise cancellation control needed for rapid SSE updates (cancel mid-tween, restart from current value to new target)
- Aligns with the "no new dependency" milestone discipline

### D-2 — Counter visual feedback: tween + one-shot bump

**Locked:**
- Counter text tweens from previous value to new value over 400ms (`--duration-medium`) ease-out
- Simultaneously, the counter cell receives a one-shot CSS class `.op-vote-counter--bump` that triggers a `scale(1) -> scale(1.06) -> scale(1)` keyframe over 300ms (`--duration-bump`)
- No color flash, no audio cue (per UI-SPEC: focal point reinforcement, not distraction)

### D-3 — Progress bar animation: existing CSS transition

**Locked:** ANIM-02 is already 90% delivered by `.op-bar-fill { transition: width 0.4s var(--ease-default); }` in operator.css line ~935. We will:
- Audit the rule and confirm width is the property changed (not transform)
- Add NO new bar animation logic — the existing setProperty('--bar-pct', ...) flow already glides
- Confirm reduced-motion globally cuts this via `design-system.css:3059`

### D-4 — prefers-reduced-motion: hard cut for all phase-3 animations

**Locked:**
- Counter tween: skip RAF, set textContent directly to target value
- Counter bump class: never added when `(prefers-reduced-motion: reduce)`
- Bar transition: handled globally by `design-system.css` reduced-motion rule (already correct)

JS check: `window.matchMedia('(prefers-reduced-motion: reduce)').matches` cached in module-level constant `PREFERS_REDUCED_MOTION`, re-checked on every tween call (cheap and correct under user-pref changes).

### D-5 — First-render guard

**Locked:** Track `_activeVoteAnimReady` keyed by motion ID. On the FIRST refresh of a new active motion, skip animation (set values directly). Subsequent SSE-driven refreshes animate.

Reason: Without this, opening exec mode causes 0 -> 47 tween that looks like votes are arriving live.

### D-6 — Snapshot/catch-up suppression

**Locked:** When `refreshExecVote()` is called from a non-SSE context (mode switch, page reload, snapshot), pass `{ silent: true }` flag to suppress bump and tween. Implementation: optional second arg.

### D-7 — What NOT to animate

**Locked:** Phase 3 animates ONLY:
- `.op-vote-counter` (Pour/Contre/Abstention numeric values)
- `.op-bar-fill` (existing transition, no change)

Explicitly NOT animated:
- Quorum value (`#opChecklistQuorumValue`, `#opFocusQuorumValue`) — quorum changes are slow and expected, animation noise
- Checklist row values (votes count, online count, SSE state)
- Chronometre — already animated by ticking
- KPI strip — already uses Anime.js helper, leave alone

### D-8 — Plan structure

Single plan, single wave: most changes are in `operator-exec.js` (counter helper + integration into existing `refreshExecVote`) and `operator.css` (bump keyframe + class). Simpler scope than Phases 1 and 2.

- **03-01-PLAN.md** — Counter tween helper + bump class + reduced-motion handling + first-render guard
