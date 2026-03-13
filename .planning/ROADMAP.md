# AG-VOTE Roadmap

## Milestones

- v1.1 through v1.5 - Phases 1-3 (shipped)
- v2.0 UI Redesign (Acte Officiel) - Phases 4-13 (in progress)

## Phases

**Phase Numbering:**
- Integer phases (4-13): Planned v2.0 milestone work
- Decimal phases (e.g., 5.1): Urgent insertions if needed (marked with INSERTED)

- [x] **Phase 4: Design Tokens & Theme** - Align color, typography, shadow, and elevation tokens with wireframe; implement dark/light theme switching (completed 2026-03-12)
- [ ] **Phase 5: Shared Components** - Build reusable component library (modals, toasts, dialogs, tags, popovers, progress bars, empty states, guided tours, session banner)
- [ ] **Phase 6: Layout & Navigation** - Implement sidebar rail/expand, header with search and notifications, mobile bottom nav, footer, ARIA landmarks
- [ ] **Phase 7: Dashboard & Sessions** - Redesign dashboard (KPIs, urgent actions, shortcuts) and sessions page (list/calendar, filters, empty states)
- [x] **Phase 8: Session Wizard & Hub** - Build 4-step create session wizard and session hub (status bar, checklist, KPIs, documents) (completed 2026-03-13)
- [ ] **Phase 9: Operator Console** - Redesign operator page with live KPI strip, progress track, resolution tabs, attendance, agenda sidebar, quorum modal
- [ ] **Phase 10: Live Session Views** - Room display (full-screen, dark) and voter tablet/mobile view (touch-optimized, large buttons)
- [ ] **Phase 11: Post-Session & Records** - Post-session stepper, archives with search/pagination, audit log with table/timeline views
- [ ] **Phase 12: Analytics & User Management** - Statistics page (KPIs, charts, export) and users management page (role panel, table, pagination)
- [ ] **Phase 13: Settings & Help** - Settings tabs (rules, communication, security, accessibility) and help/FAQ page (accordion, guided tour launchers)

## Phase Details

### Phase 4: Design Tokens & Theme
**Goal**: The design system CSS produces the exact visual language defined in wireframe v3.19.2 -- every color, font, shadow, and surface matches
**Depends on**: Nothing (foundation for all other phases)
**Requirements**: DS-01, DS-02, DS-03, DS-04, DS-05
**Success Criteria** (what must be TRUE):
  1. All CSS custom properties for colors, typography, shadows, borders, and radius match the wireframe token values
  2. Toggling between dark and light theme via `[data-theme]` switches all surfaces, text, and borders without visual artifacts
  3. Surface elevation hierarchy (bg, surface, surface-alt, surface-raised, glass) is visually distinguishable at each level
  4. Semantic color tokens (danger, success, warn, purple) render correctly in both themes with matching bg/border variants
  5. Body text renders in Bricolage Grotesque, display headings in Fraunces, and data/code in JetBrains Mono
**Plans:** 2/2 plans complete
Plans:
- [ ] 04-01-PLAN.md — Validation script + light theme token alignment with wireframe
- [ ] 04-02-PLAN.md — Dark theme token alignment + visual verification

### Phase 5: Shared Components
**Goal**: A complete library of reusable UI components exists that all pages can consume for consistent interaction patterns
**Depends on**: Phase 4
**Requirements**: COMP-01, COMP-02, COMP-03, COMP-04, COMP-05, COMP-06, COMP-07, COMP-08, COMP-09
**Success Criteria** (what must be TRUE):
  1. Opening a modal displays a centered dialog with header/body/footer over a backdrop overlay, and pressing Escape or clicking backdrop closes it
  2. Confirmation dialogs render in danger/warn/info variants with appropriate icon, and block the action until user confirms or cancels
  3. Toast notifications appear at a screen edge for success/warn/error/info events and auto-dismiss after a configurable delay
  4. Empty state, tag/badge, progress bar, and popover menu components render correctly and are usable by any page
  5. The guided tour system highlights elements step-by-step using data-tour targets, and the session expiry banner offers stay-logged-in / logout actions
**Plans:** 3/4 plans executed
Plans:
- [ ] 05-01-PLAN.md — Modal, confirm dialog, and toast token alignment
- [ ] 05-02-PLAN.md — Badge, empty state, progress bar, and popover token alignment
- [ ] 05-03-PLAN.md — Session expiry banner and guided tour CSS tokenization
- [ ] 05-04-PLAN.md — Cross-component validation and dark/light theme visual verification

### Phase 6: Layout & Navigation
**Goal**: The application shell (sidebar, header, footer, mobile nav) matches the wireframe layout and provides consistent navigation across all pages
**Depends on**: Phase 4
**Requirements**: NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06
**Success Criteria** (what must be TRUE):
  1. Sidebar shows a 58px icon rail by default, expands to 252px on hover as an overlay without shifting page content, and can be pinned open
  2. Sidebar navigation is organized into 5 labeled sections (Preparation, Seance en direct, Apres la seance, Controle, Systeme) with correct items in each
  3. Header bar (56px) displays the logo, a global search triggered by Cmd+K, a notification panel, and a theme toggle
  4. On mobile viewports, a bottom navigation bar with 5 tabs (Dashboard, Sessions, Fiche, Operateur, Parametres) replaces the sidebar
  5. A skip-to-content link is present and ARIA landmark roles are applied to nav, main, header, and footer regions
**Plans:** 1/3 plans executed
Plans:
- [ ] 06-01-PLAN.md — Sidebar structure alignment (5 sections, nav-badge CSS, mobile-bnav CSS)
- [ ] 06-02-PLAN.md — Header logo, mobile 5-tab bottom nav, footer on all pages
- [ ] 06-03-PLAN.md — ARIA landmark audit and visual verification checkpoint

### Phase 7: Dashboard & Sessions
**Goal**: Users see an actionable dashboard with KPIs and shortcuts, and can browse/search/filter all sessions in list or calendar view
**Depends on**: Phase 5, Phase 6
**Requirements**: DASH-01, DASH-02, DASH-03, DASH-04, SESS-01, SESS-02, SESS-03, SESS-04, SESS-05
**Success Criteria** (what must be TRUE):
  1. Dashboard displays 4 KPI cards (AG a venir, En cours, Convocations en attente, PV a envoyer), an urgent action card when needed, and 3 shortcut cards
  2. Dashboard shows a 2-column layout with upcoming sessions list on the left and a prioritized task list on the right
  3. Sessions page lets the user toggle between list and calendar views, search by text, sort, and filter by status pills (Toutes, A venir, En cours, Terminees) with counts
  4. Session list items display status dot, title, date, participants count, resolutions count, quorum, status tag, and a popover action menu
  5. When no sessions exist, an empty state with icon, title, subtitle, and CTA button is shown
**Plans:** 3 plans
Plans:
- [ ] 07-01-PLAN.md — Dashboard HTML/CSS/JS refactor: KPI cards, urgent card, layout, shortcuts (inline styles to CSS classes)
- [ ] 07-02-PLAN.md — Sessions page HTML/CSS restructure: remove wizard, replace stats bar with filter pills, session list item layout
- [ ] 07-03-PLAN.md — Sessions JS rewrite: filter pills, search/sort, list rendering, calendar view, popover menus, empty states

### Phase 8: Session Wizard & Hub
**Goal**: Users can create a session through a guided 4-step wizard and manage session preparation from a central hub with status tracking
**Depends on**: Phase 5, Phase 6
**Requirements**: WIZ-01, WIZ-02, WIZ-03, WIZ-04, WIZ-05, HUB-01, HUB-02, HUB-03, HUB-04, HUB-05
**Success Criteria** (what must be TRUE):
  1. Create Session wizard displays a 4-step accordion with a visual stepper showing done/active/pending states, and each step expands to reveal its form
  2. Wizard steps cover general info (title, type, date, time, location), members (CSV import, manual entry, lot, vote weight), agenda (resolutions, voting rules, secret ballot), and recap (review, create, download PDF)
  3. Session Hub shows a colorful status bar representing session stages and a prominent main action card for the next step
  4. Hub displays 4 KPI cards (participants, resolutions, quorum needed, convocations), a preparation checklist with completion tracking, and a documents panel
**Plans**: 3 plans
Plans:
- [x] 08-01-PLAN.md — Wizard HTML/CSS refactor (wizard.css) + JS features (localStorage draft, drag-drop, API wire, validation)
- [x] 08-02-PLAN.md — Hub HTML/CSS refactor (hub.css from operator.css) + JS features (status bar, checklist, action card)
- [ ] 08-03-PLAN.md — Gap closure: wizard PDF download + hub API wiring with demo fallback

### Phase 9: Operator Console
**Goal**: Operators can run a live session from a single page with real-time KPIs, resolution management, attendance tracking, and agenda navigation
**Depends on**: Phase 5, Phase 6
**Requirements**: OPR-01, OPR-02, OPR-03, OPR-04, OPR-05, OPR-06, OPR-07, OPR-08, OPR-09, OPR-10
**Success Criteria** (what must be TRUE):
  1. Operator header shows a live dot, session title, running timer (HH:MM:SS), room display button, and close session button
  2. KPI strip displays Presents (x/y), Quorum (% + check), Ont vote (x/y), Resolution (x/y) with tags; progress track shows 5 color-coded segments for resolution status
  3. Active resolution card shows live dot, title, tags (majorite, cle, secret), and 3 sub-tabs: Resultat (vote toggle, proclamer, progress bar, Pour/Contre/Abstention bars), Avance (manual counts, didn't-vote list, unanimity/proxy/suspend, secretary notes), Presences (mini KPIs, attendance table with toggles)
  4. Right sidebar shows resolution agenda list with status circles and current resolution highlighted; bottom action bar has Proclamer (P shortcut) and Vote toggle (F shortcut)
  5. When quorum is lost, a blocking modal appears with 3 action buttons (reporter, suspendre, continuer)
**Plans**: 3 plans
Plans:
- [ ] 09-01-PLAN.md — Inline styles cleanup, execution header with live indicators, KPI strip redesign, progress track
- [ ] 09-02-PLAN.md — Resolution card with sub-tabs (Resultat/Avance/Presences), right sidebar agenda list
- [ ] 09-03-PLAN.md — Quorum modal, action bar JS, keyboard shortcuts, agenda rendering, execution wiring

### Phase 10: Live Session Views
**Goal**: The room display shows vote results on a large screen and voters can participate from tablets/phones with touch-optimized controls
**Depends on**: Phase 5, Phase 9
**Requirements**: DISP-01, DISP-02, VOTE-01, VOTE-02, VOTE-03
**Success Criteria** (what must be TRUE):
  1. Room display fills the entire screen with no header/sidebar, uses a dark background (#0B0F1A), and shows session title, current resolution, live result bars, participation %, timer, and status
  2. Voter view renders a touch-optimized layout with bottom navigation on tablet/mobile viewports
  3. Voters see a large resolution title with big vote buttons (Pour/Contre/Abstention), a hand raise button, a vote confirmation screen, countdown timer, and present/absent toggle
**Plans**: TBD
Plans:
- (to be planned)

### Phase 11: Post-Session & Records
**Goal**: Users complete post-session workflow (verify, validate, generate PV, send), browse archived sessions, and review audit logs
**Depends on**: Phase 5, Phase 6
**Requirements**: POST-01, POST-02, POST-03, ARCH-01, ARCH-02, AUD-01, AUD-02, AUD-03
**Success Criteria** (what must be TRUE):
  1. Post-session page shows a 4-step stepper (Verification, Validation, PV, Envoi) with per-step checklist items, action buttons, and status indicators
  2. Post-session provides document download (PV), e-signature request, and send-to-all functionality
  3. Archives page displays searchable archive cards (title, date, type, resolution summary, attendance) with pagination (5 per page) and detail view on click
  4. Audit page offers filter by event type, table/timeline view toggle, search/sort, table rows with date/time/user/action/resource/status/details, and an event detail modal
**Plans**: TBD
Plans:
- (to be planned)

### Phase 12: Analytics & User Management
**Goal**: Administrators can view voting statistics with charts and manage users with role assignments
**Depends on**: Phase 5, Phase 6
**Requirements**: STAT-01, STAT-02, STAT-03, USR-01, USR-02, USR-03
**Success Criteria** (what must be TRUE):
  1. Statistics page displays 4 KPI cards (Sessions, Resolutions, Taux d'adoption, Participation) with trend arrows
  2. Statistics page shows a donut chart for vote distribution (Pour/Contre/Abstention) and a line graph for participation trends, with an export button
  3. Users page shows a role info panel describing Admin/Gestionnaire/Operateur roles, a users table with avatar/name/email/role tag/status/last login/edit, and add user + pagination controls
**Plans**: TBD
Plans:
- (to be planned)

### Phase 13: Settings & Help
**Goal**: Administrators can configure application rules, communication, security, and accessibility settings, and users can access FAQ and guided tours
**Depends on**: Phase 5, Phase 6
**Requirements**: SET-01, SET-02, SET-03, SET-04, FAQ-01, FAQ-02
**Success Criteria** (what must be TRUE):
  1. Settings page has 4 tabs: Regles (double auth/approval toggles, quorum settings), Communication (support email, email templates, notification prefs), Securite (2FA, session timeout), Accessibilite (text size A/A+/A++, high contrast, focus indicators)
  2. Help page shows an accordion FAQ with category filter and search
  3. Help page provides guided tour launcher buttons for Dashboard, Operator, Members, Hub, Stats, and Post-Session workflows
**Plans**: TBD
Plans:
- (to be planned)

## Progress

**Execution Order:**
Phases execute in numeric order: 4 -> 5 -> 6 -> 7 -> 8 -> 9 -> 10 -> 11 -> 12 -> 13
(Phases 7, 8, 9, 11, 12, 13 all depend on 5+6 and could parallelize, but sequential is safer for solo workflow.)

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 4. Design Tokens & Theme | 2/2 | Complete    | 2026-03-12 |
| 5. Shared Components | 3/4 | In Progress|  |
| 6. Layout & Navigation | 1/3 | In Progress|  |
| 7. Dashboard & Sessions | 0/3 | Not started | - |
| 8. Session Wizard & Hub | 3/3 | Complete   | 2026-03-13 |
| 9. Operator Console | 2/3 | In Progress|  |
| 10. Live Session Views | 0/TBD | Not started | - |
| 11. Post-Session & Records | 0/TBD | Not started | - |
| 12. Analytics & User Management | 0/TBD | Not started | - |
| 13. Settings & Help | 0/TBD | Not started | - |

---

<details>
<summary>Previous Milestones (v1.1 - v1.5) -- Phases 1-3</summary>

### v1.5 — E2E Coverage Expansion & Release (COMPLETE)

- Phase 1: Operator & Dashboard E2E -- done
- Phase 2: Report, Validate & Archives E2E -- done
- Phase 3: Version Bump & Release -- done

### v1.4 — Test Coverage & Final Polish (COMPLETE)

3 phases: 100% controller tests, Permissions-Policy header, dead code audit.

### v1.3 — Code Quality & Frontend Cleanup (COMPLETE)

3 phases: unused vars fixed (142->0), innerHTML triaged safe, CI lint ratchet.

### v1.2 — Security & Resilience Hardening (COMPLETE)

4 phases: tenant isolation, rate limiting, PWA hardening, audit verification.

### v1.1 — Post-Audit Hardening (COMPLETE)

6 phases: E2E suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility.

</details>
