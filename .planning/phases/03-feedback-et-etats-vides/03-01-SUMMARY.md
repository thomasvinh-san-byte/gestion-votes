---
phase: 03-feedback-et-etats-vides
plan: 01
subsystem: ui
tags: [htmx, javascript, css, loading-states, vote, accessibility]

# Dependency graph
requires:
  - phase: 01-typographie-et-tokens
    provides: CSS design tokens (--text-sm, --space-1, --space-2, --color-text-muted)
  - phase: 02-sidebar-navigation
    provides: app-shell layout with htmx-indicator pattern established
provides:
  - Vote confirmation persistante avec horodatage francais (sans setTimeout 3s)
  - Classe CSS .loading-label pour texte visible pendant chargement
  - Texte Chargement... visible sur 5 pages listes (meetings, members, users, audit, vote)
affects: [04-etats-vides, 05-validation-gate]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - loading-label class for visible text alongside skeleton loaders
    - French timestamp format JJ/MM/AAAA a HH:MM via JS Date API
    - role=status on persistent confirmation states for screen reader announcement

key-files:
  created: []
  modified:
    - public/assets/js/pages/vote.js
    - public/vote.htmx.html
    - public/assets/css/design-system.css
    - public/meetings.htmx.html
    - public/members.htmx.html
    - public/users.htmx.html
    - public/audit.htmx.html

key-decisions:
  - "Vote confirmation stays visible indefinitely — state resets naturally via SSE when next motion opens"
  - "loading-label is a block span, not inline — allows stacking above skeleton rows"
  - "audit.htmx.html: spinner aria-label moved to visible span, spinner gets aria-hidden to avoid duplication"

patterns-established:
  - "loading-label pattern: <span class='loading-label' aria-live='polite'>Chargement...</span> as first child of loading region"
  - "htmx-indicator: remove aria-hidden when adding loading-label so screen readers announce the text"

requirements-completed: [FEED-02, FEED-04]

# Metrics
duration: 15min
completed: 2026-04-21
---

# Phase 3 Plan 01: Feedback et Etats Vides — Confirmation + Chargement Summary

**Vote confirmation persistante avec horodatage JJ/MM/AAAA a HH:MM (setTimeout 3s supprime) + texte Chargement... visible sur 5 pages via classe .loading-label**

## Performance

- **Duration:** 15 min
- **Started:** 2026-04-21T09:10:00Z
- **Completed:** 2026-04-21T09:25:00Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- Suppression du setTimeout 3s dans showConfirmationState() — la confirmation reste visible jusqu'au prochain vote SSE
- Ajout de l'horodatage francais "Vote enregistre le JJ/MM/AAAA a HH:MM" dans l'element #voteConfirmedTimestamp
- Ajout de role="status" sur #voteConfirmedState pour annonce screen reader
- Nouvelle classe CSS .loading-label (block, text-sm, color-text-muted) + .vote-confirmed-timestamp
- Texte "Chargement..." visible sur meetings, members, users, audit (x2), vote — aria-live="polite" sur meetings et members

## Task Commits

1. **Task 1: Vote confirmation persistante + horodatage + CSS loading-label** - `4278667a` (feat)
2. **Task 2: Texte Chargement... sur toutes les pages listes** - `58357fba` (feat)

## Files Created/Modified

- `public/assets/js/pages/vote.js` - showConfirmationState() sans setTimeout, avec horodatage francais
- `public/vote.htmx.html` - #voteConfirmedTimestamp, role=status, loading-label dans voteLoadingState
- `public/assets/css/design-system.css` - .loading-label et .vote-confirmed-timestamp classes
- `public/meetings.htmx.html` - loading-label dans htmx-indicator, aria-hidden supprime
- `public/members.htmx.html` - loading-label dans htmx-indicator, aria-hidden supprime
- `public/users.htmx.html` - loading-label avant skeleton rows dans #usersTableBody
- `public/audit.htmx.html` - loading-label dans tbody et timeline, spinner aria-hidden

## Decisions Made

- Vote confirmation reste visible indefiniment : l'etat revient a "waiting" via SSE quand la prochaine motion s'ouvre — pas besoin de timer artificiel
- loading-label est un element block (span display:block) pour s'empiler proprement au-dessus des skeleton rows
- Sur audit.htmx.html : l'aria-label="Chargement..." du spinner a ete deplace vers un span visible + le spinner passe en aria-hidden pour eviter la double annonce

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- FEED-02 et FEED-04 completement satisfaits
- .loading-label disponible pour tous les composants de chargement futurs
- Phase 4 (etats vides) peut utiliser les memes patterns de classe CSS

---
*Phase: 03-feedback-et-etats-vides*
*Completed: 2026-04-21*
