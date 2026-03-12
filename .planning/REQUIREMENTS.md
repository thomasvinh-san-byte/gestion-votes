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

- [ ] **NAV-01**: Sidebar with 58px rail (icons only), 252px expanded on hover (overlay, no layout shift), pinnable
- [ ] **NAV-02**: Sidebar organized in 5 sections: Préparation, Séance en direct, Après la séance, Contrôle, Système
- [ ] **NAV-03**: Header bar (56px) with logo, global search (Cmd+K overlay), notification panel, theme toggle
- [ ] **NAV-04**: Mobile bottom navigation with 5 primary tabs (Dashboard, Sessions, Fiche, Opérateur, Paramètres)
- [ ] **NAV-05**: Footer with logo, help link, accessibility link
- [ ] **NAV-06**: Skip-to-content link and ARIA navigation landmarks

### Dashboard

- [ ] **DASH-01**: 4 KPI cards (AG à venir, En cours, Convocations en attente, PV à envoyer)
- [ ] **DASH-02**: Urgent action card (red, large, clickable) when action needed
- [ ] **DASH-03**: 2-column grid: upcoming sessions list (left) + task list with priority colors (right)
- [ ] **DASH-04**: 3-column shortcut cards (Créer séance, Piloter vote, Piste audit)

### Sessions

- [ ] **SESS-01**: List/calendar view toggle with search bar and sort dropdown
- [ ] **SESS-02**: Filter pills (Toutes, À venir, En cours, Terminées) with counts
- [ ] **SESS-03**: Session list items with status dot, title, date, participants, resolutions, quorum, status tag, popover menu
- [ ] **SESS-04**: Calendar view with month display, color-coded event cells
- [ ] **SESS-05**: Empty state with icon, title, subtitle, CTA button

### Create Session Wizard

- [ ] **WIZ-01**: 4-step accordion with visual stepper (done/active/pending circles)
- [ ] **WIZ-02**: Step 1 — Infos générales: title, type (select), date, time, location, address
- [ ] **WIZ-03**: Step 2 — Membres: CSV import, manual entry table, lot assignment, vote weight
- [ ] **WIZ-04**: Step 3 — Ordre du jour: resolution entries, voting rule per resolution, secret ballot toggle
- [ ] **WIZ-05**: Step 4 — Récapitulatif: review all info, create button, download PDF option

### Session Hub

- [ ] **HUB-01**: Status bar with colorful segments representing session stages
- [ ] **HUB-02**: Main action card (highlighted, large CTA) for next step
- [ ] **HUB-03**: 4 KPI cards (participants, resolutions, quorum needed, convocations)
- [ ] **HUB-04**: Preparation checklist with completion tracking
- [ ] **HUB-05**: Associated documents panel with download links

### Operator

- [ ] **OPR-01**: Header bar with live dot, session title, timer (HH:MM:SS), room display button, close session button
- [ ] **OPR-02**: KPI strip: Présents (x/y), Quorum (% + check), Ont voté (x/y), Résolution (x/y), tags
- [ ] **OPR-03**: Progress track with 5 horizontal segments (voted/voting/pending color-coded)
- [ ] **OPR-04**: Resolution card with live dot, title, tags (majorité, clé, secret), 3 sub-tabs
- [ ] **OPR-05**: Tab Résultat: vote toggle, proclamer button, progress bar, 3 result bars (Pour/Contre/Abstention)
- [ ] **OPR-06**: Tab Avancé: manual count inputs, didn't-vote list, unanimity/proxy/suspend actions, secretary notes
- [ ] **OPR-07**: Tab Présences: 4 mini KPI cards, attendance table with status toggles
- [ ] **OPR-08**: Right sidebar with resolution agenda list (status circles, current highlighted)
- [ ] **OPR-09**: Quorum warning modal (blocking, 3 action buttons: reporter, suspendre, continuer)
- [ ] **OPR-10**: Bottom action bar with Proclamer (P shortcut) and Vote toggle (F shortcut)

### Room Display

- [ ] **DISP-01**: Full-screen layout (no header/sidebar), dark background (#0B0F1A)
- [ ] **DISP-02**: Session title, current resolution, live result bars, participation %, timer, status

### Post-Session

- [ ] **POST-01**: 4-step stepper: Vérification, Validation, PV, Envoi
- [ ] **POST-02**: Per-step checklist items with action buttons and status indicators
- [ ] **POST-03**: Document download (PV), e-signature request, send-to-all buttons

### Archives

- [ ] **ARCH-01**: Search bar with archive cards showing title, date, type, resolution summary, attendance
- [ ] **ARCH-02**: Pagination (5 per page) and detail view on click

### Audit

- [ ] **AUD-01**: Filter by event type, view toggle (table/timeline), search/sort
- [ ] **AUD-02**: Table view with date/time, user action, resource, status, details button
- [ ] **AUD-03**: Event detail modal

### Statistics

- [ ] **STAT-01**: 4 KPI cards (Sessions, Résolutions, Taux d'adoption, Participation) with trend arrows
- [ ] **STAT-02**: Donut chart (Pour/Contre/Abstention distribution) + line graph (participation trends)
- [ ] **STAT-03**: Export button

### Users

- [ ] **USR-01**: Role info panel (Admin, Gestionnaire, Opérateur) with descriptions
- [ ] **USR-02**: Users table with avatar, name, email, role tag (color-coded), status, last login, edit button
- [ ] **USR-03**: Add user button + pagination

### Settings

- [ ] **SET-01**: Tab Règles: double auth toggle, double approval toggle, quorum base/percentage
- [ ] **SET-02**: Tab Communication: support email, email templates preview, notification preferences
- [ ] **SET-03**: Tab Sécurité: 2FA management, session timeout
- [ ] **SET-04**: Tab Accessibilité: text size (A/A+/A++), high contrast toggle, focus indicators

### Help & FAQ

- [ ] **FAQ-01**: Accordion FAQ with category filter and search
- [ ] **FAQ-02**: Guided tour launcher buttons (Dashboard, Operator, Members, Hub, Stats, Post-Session)

### Voter View

- [ ] **VOTE-01**: Touch-optimized tablet/mobile layout with bottom navigation
- [ ] **VOTE-02**: Large resolution title, big vote buttons (Pour/Contre/Abstention), hand raise button
- [ ] **VOTE-03**: Vote confirmation screen, countdown timer, present/absent toggle

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
| NAV-01 | Phase 6 | Pending |
| NAV-02 | Phase 6 | Pending |
| NAV-03 | Phase 6 | Pending |
| NAV-04 | Phase 6 | Pending |
| NAV-05 | Phase 6 | Pending |
| NAV-06 | Phase 6 | Pending |
| DASH-01 | Phase 7 | Pending |
| DASH-02 | Phase 7 | Pending |
| DASH-03 | Phase 7 | Pending |
| DASH-04 | Phase 7 | Pending |
| SESS-01 | Phase 7 | Pending |
| SESS-02 | Phase 7 | Pending |
| SESS-03 | Phase 7 | Pending |
| SESS-04 | Phase 7 | Pending |
| SESS-05 | Phase 7 | Pending |
| WIZ-01 | Phase 8 | Pending |
| WIZ-02 | Phase 8 | Pending |
| WIZ-03 | Phase 8 | Pending |
| WIZ-04 | Phase 8 | Pending |
| WIZ-05 | Phase 8 | Pending |
| HUB-01 | Phase 8 | Pending |
| HUB-02 | Phase 8 | Pending |
| HUB-03 | Phase 8 | Pending |
| HUB-04 | Phase 8 | Pending |
| HUB-05 | Phase 8 | Pending |
| OPR-01 | Phase 9 | Pending |
| OPR-02 | Phase 9 | Pending |
| OPR-03 | Phase 9 | Pending |
| OPR-04 | Phase 9 | Pending |
| OPR-05 | Phase 9 | Pending |
| OPR-06 | Phase 9 | Pending |
| OPR-07 | Phase 9 | Pending |
| OPR-08 | Phase 9 | Pending |
| OPR-09 | Phase 9 | Pending |
| OPR-10 | Phase 9 | Pending |
| DISP-01 | Phase 10 | Pending |
| DISP-02 | Phase 10 | Pending |
| VOTE-01 | Phase 10 | Pending |
| VOTE-02 | Phase 10 | Pending |
| VOTE-03 | Phase 10 | Pending |
| POST-01 | Phase 11 | Pending |
| POST-02 | Phase 11 | Pending |
| POST-03 | Phase 11 | Pending |
| ARCH-01 | Phase 11 | Pending |
| ARCH-02 | Phase 11 | Pending |
| AUD-01 | Phase 11 | Pending |
| AUD-02 | Phase 11 | Pending |
| AUD-03 | Phase 11 | Pending |
| STAT-01 | Phase 12 | Pending |
| STAT-02 | Phase 12 | Pending |
| STAT-03 | Phase 12 | Pending |
| USR-01 | Phase 12 | Pending |
| USR-02 | Phase 12 | Pending |
| USR-03 | Phase 12 | Pending |
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
