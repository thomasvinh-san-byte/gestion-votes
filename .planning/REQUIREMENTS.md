# Requirements: AG-VOTE v2.0 — UI Redesign

**Defined:** 2026-03-12
**Core Value:** Align all pages and components with the AG-Vote v3.19.2 "Acte Officiel" wireframe

## v1 Requirements

Requirements for v2.0 release. Each maps to roadmap phases.

### Design System

- [x] **DS-01**: Design tokens (colors, typography, shadows, borders, radius) match wireframe v3.19.2
- [x] **DS-02**: Dark/light theme tokens fully implemented with `[data-theme]` switching
- [x] **DS-03**: Surface elevation system (bg, surface, surface-alt, surface-raised, glass) applied consistently
- [x] **DS-04**: Semantic color tokens (danger, success, warn, purple) with bg/border variants
- [x] **DS-05**: Typography system uses Bricolage Grotesque (body), Fraunces (display), JetBrains Mono (data)

### Layout & Navigation

- [x] **NAV-01**: Sidebar with 58px rail (icons only), 252px expanded on hover (overlay, no layout shift), pinnable
- [x] **NAV-02**: Sidebar organized in 5 sections: Préparation, Séance en direct, Après la séance, Contrôle, Système
- [x] **NAV-03**: Header bar (56px) with logo, global search (Cmd+K overlay), notification panel, theme toggle
- [x] **NAV-04**: Mobile bottom navigation with 5 primary tabs (Dashboard, Sessions, Fiche, Opérateur, Paramètres)
- [x] **NAV-05**: Footer with logo, help link, accessibility link
- [x] **NAV-06**: Skip-to-content link and ARIA navigation landmarks

### Dashboard

- [x] **DASH-01**: 4 KPI cards (AG à venir, En cours, Convocations en attente, PV à envoyer)
- [x] **DASH-02**: Urgent action card (red, large, clickable) when action needed
- [x] **DASH-03**: 2-column grid: upcoming sessions list (left) + task list with priority colors (right)
- [x] **DASH-04**: 3-column shortcut cards (Créer séance, Piloter vote, Piste audit)

### Sessions

- [x] **SESS-01**: List/calendar view toggle with search bar and sort dropdown
- [x] **SESS-02**: Filter pills (Toutes, À venir, En cours, Terminées) with counts
- [x] **SESS-03**: Session list items with status dot, title, date, participants, resolutions, quorum, status tag, popover menu
- [x] **SESS-04**: Calendar view with month display, color-coded event cells
- [x] **SESS-05**: Empty state with icon, title, subtitle, CTA button

### Create Session Wizard

- [x] **WIZ-01**: 4-step accordion with visual stepper (done/active/pending circles)
- [x] **WIZ-02**: Step 1 — Infos générales: title, type (select), date, time, location, address
- [x] **WIZ-03**: Step 2 — Membres: CSV import, manual entry table, lot assignment, vote weight
- [x] **WIZ-04**: Step 3 — Ordre du jour: resolution entries, voting rule per resolution, secret ballot toggle
- [x] **WIZ-05**: Step 4 — Récapitulatif: review all info, create button, download PDF option

### Session Hub

- [x] **HUB-01**: Status bar with colorful segments representing session stages
- [x] **HUB-02**: Main action card (highlighted, large CTA) for next step
- [x] **HUB-03**: 4 KPI cards (participants, resolutions, quorum needed, convocations)
- [x] **HUB-04**: Preparation checklist with completion tracking
- [x] **HUB-05**: Associated documents panel with download links

### Operator

- [x] **OPR-01**: Header bar with live dot, session title, timer (HH:MM:SS), room display button, close session button
- [x] **OPR-02**: KPI strip: Présents (x/y), Quorum (% + check), Ont voté (x/y), Résolution (x/y), tags
- [x] **OPR-03**: Progress track with 5 horizontal segments (voted/voting/pending color-coded)
- [x] **OPR-04**: Resolution card with live dot, title, tags (majorité, clé, secret), 3 sub-tabs
- [x] **OPR-05**: Tab Résultat: vote toggle, proclamer button, progress bar, 3 result bars (Pour/Contre/Abstention)
- [x] **OPR-06**: Tab Avancé: manual count inputs, didn't-vote list, unanimity/proxy/suspend actions, secretary notes
- [x] **OPR-07**: Tab Présences: 4 mini KPI cards, attendance table with status toggles
- [x] **OPR-08**: Right sidebar with resolution agenda list (status circles, current highlighted)
- [x] **OPR-09**: Quorum warning modal (blocking, 3 action buttons: reporter, suspendre, continuer)
- [x] **OPR-10**: Bottom action bar with Proclamer (P shortcut) and Vote toggle (F shortcut)

### Room Display

- [x] **DISP-01**: Full-screen layout (no header/sidebar), dark background (#0B0F1A)
- [x] **DISP-02**: Session title, current resolution, live result bars, participation %, timer, status

### Post-Session

- [x] **POST-01**: 4-step stepper: Vérification, Validation, PV, Envoi
- [x] **POST-02**: Per-step checklist items with action buttons and status indicators
- [x] **POST-03**: Document download (PV), e-signature request, send-to-all buttons

### Archives

- [x] **ARCH-01**: Search bar with archive cards showing title, date, type, resolution summary, attendance
- [x] **ARCH-02**: Pagination (5 per page) and detail view on click

### Audit

- [x] **AUD-01**: Filter by event type, view toggle (table/timeline), search/sort
- [x] **AUD-02**: Table view with date/time, user action, resource, status, details button
- [x] **AUD-03**: Event detail modal

### Statistics

- [x] **STAT-01**: 4 KPI cards (Sessions, Résolutions, Taux d'adoption, Participation) with trend arrows
- [x] **STAT-02**: Donut chart (Pour/Contre/Abstention distribution) + line graph (participation trends)
- [x] **STAT-03**: Export button

### Users

- [x] **USR-01**: Role info panel (Admin, Opérateur, Auditeur, Observateur) with descriptions
- [x] **USR-02**: Users table with avatar, name, email, role tag (color-coded), status, last login, edit button
- [x] **USR-03**: Add user button + pagination

### Settings

- [ ] **SET-01**: Tab Règles: double auth toggle, double approval toggle, quorum base/percentage
- [ ] **SET-02**: Tab Communication: support email, email templates preview, notification preferences
- [ ] **SET-03**: Tab Sécurité: 2FA management, session timeout
- [ ] **SET-04**: Tab Accessibilité: text size (A/A+/A++), high contrast toggle, focus indicators

### Help & FAQ

- [ ] **FAQ-01**: Accordion FAQ with category filter and search
- [ ] **FAQ-02**: Guided tour launcher buttons (Dashboard, Operator, Members, Hub, Stats, Post-Session)

### Voter View

- [x] **VOTE-01**: Touch-optimized tablet/mobile layout with bottom navigation
- [x] **VOTE-02**: Large resolution title, big vote buttons (Pour/Contre/Abstention), hand raise button
- [x] **VOTE-03**: Vote confirmation screen, countdown timer, present/absent toggle

### Shared Components

- [x] **COMP-01**: Modal system (center dialog with header/body/footer, overlay backdrop)
- [x] **COMP-02**: Confirmation dialogs (danger/warn/info variants with icon)
- [x] **COMP-03**: Toast notification system (success/warn/error/info, auto-dismiss)
- [x] **COMP-04**: Empty state component (icon + title + subtitle + CTA)
- [x] **COMP-05**: Tag/badge system (danger, success, warn, accent, purple variants)
- [x] **COMP-06**: Progress bars and mini bar charts (vote distribution)
- [x] **COMP-07**: Popover menus (action dropdowns)
- [x] **COMP-08**: Session expiry warning banner (stay logged in / logout)
- [x] **COMP-09**: Guided tour system (step-by-step walkthrough with data-tour targets)

## v2 Requirements (Deferred)

### Signatures Électroniques (v2.1)

- **SIG-01**: Upload de signature électronique par image (PNG/JPG)
- **SIG-02**: Validation des signatures dans le workflow Post-Session
- **SIG-03**: Règles de validation pour les signatures (format, taille, authenticité)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Framework migration (React, Vue, etc.) | Vanilla stack is the project identity |
| New voting modes | Functional feature, not UI redesign |
| New report types | Functional feature, not UI redesign |
| Mobile native app | PWA approach maintained |
| Multi-database support | PostgreSQL only |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DS-01 | Phase 4 | Complete |
| DS-02 | Phase 4 | Complete |
| DS-03 | Phase 4 | Complete |
| DS-04 | Phase 4 | Complete |
| DS-05 | Phase 4 | Complete |
| COMP-01 | Phase 5 | Complete |
| COMP-02 | Phase 5 | Complete |
| COMP-03 | Phase 5 | Complete |
| COMP-04 | Phase 5 | Complete |
| COMP-05 | Phase 5 | Complete |
| COMP-06 | Phase 5 | Complete |
| COMP-07 | Phase 5 | Complete |
| COMP-08 | Phase 5 | Complete |
| COMP-09 | Phase 5 | Complete |
| NAV-01 | Phase 6 | Complete |
| NAV-02 | Phase 6 | Complete |
| NAV-03 | Phase 6 | Complete |
| NAV-04 | Phase 6 | Complete |
| NAV-05 | Phase 6 | Complete |
| NAV-06 | Phase 6 | Complete |
| DASH-01 | Phase 7 | Complete |
| DASH-02 | Phase 7 | Complete |
| DASH-03 | Phase 7 | Complete |
| DASH-04 | Phase 7 | Complete |
| SESS-01 | Phase 7 | Complete |
| SESS-02 | Phase 7 | Complete |
| SESS-03 | Phase 7 | Complete |
| SESS-04 | Phase 7 | Complete |
| SESS-05 | Phase 7 | Complete |
| WIZ-01 | Phase 8 | Complete |
| WIZ-02 | Phase 8 | Complete |
| WIZ-03 | Phase 8 | Complete |
| WIZ-04 | Phase 8 | Complete |
| WIZ-05 | Phase 8 | Complete |
| HUB-01 | Phase 8 | Complete |
| HUB-02 | Phase 8 | Complete |
| HUB-03 | Phase 8 | Complete |
| HUB-04 | Phase 8 | Complete |
| HUB-05 | Phase 8 | Complete |
| OPR-01 | Phase 9 | Complete |
| OPR-02 | Phase 9 | Complete |
| OPR-03 | Phase 9 | Complete |
| OPR-04 | Phase 9 | Complete |
| OPR-05 | Phase 9 | Complete |
| OPR-06 | Phase 9 | Complete |
| OPR-07 | Phase 9 | Complete |
| OPR-08 | Phase 9 | Complete |
| OPR-09 | Phase 9 | Complete |
| OPR-10 | Phase 9 | Complete |
| DISP-01 | Phase 10 | Complete |
| DISP-02 | Phase 10 | Complete |
| VOTE-01 | Phase 10 | Complete |
| VOTE-02 | Phase 10 | Complete |
| VOTE-03 | Phase 10 | Complete |
| POST-01 | Phase 11 | Complete |
| POST-02 | Phase 11 | Complete |
| POST-03 | Phase 11 | Complete |
| ARCH-01 | Phase 11 | Complete |
| ARCH-02 | Phase 11 | Complete |
| AUD-01 | Phase 11 | Complete |
| AUD-02 | Phase 11 | Complete |
| AUD-03 | Phase 11 | Complete |
| STAT-01 | Phase 12 | Complete |
| STAT-02 | Phase 12 | Complete |
| STAT-03 | Phase 12 | Complete |
| USR-01 | Phase 12 | Complete |
| USR-02 | Phase 12 | Complete |
| USR-03 | Phase 12 | Complete |
| SET-01 | Phase 13 | Pending |
| SET-02 | Phase 13 | Pending |
| SET-03 | Phase 13 | Pending |
| SET-04 | Phase 13 | Pending |
| FAQ-01 | Phase 13 | Pending |
| FAQ-02 | Phase 13 | Pending |

**Coverage:**
- v1 requirements: 74 total
- Mapped to phases: 74
- Unmapped: 0

---
*Requirements defined: 2026-03-12*
*Last updated: 2026-03-12 after roadmap creation*
