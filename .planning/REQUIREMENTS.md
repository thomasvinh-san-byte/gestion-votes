# Requirements: AG-VOTE v4.2 "Visual Redesign"

**Defined:** 2026-03-19
**Core Value:** Every page looks top 1% — professionally designed, intuitive, self-explanatory

## v4.2 Requirements

### UX Transversal (UX)

- [ ] **UX-01**: Every page is self-explanatory — tooltips on complex elements, contextual help via hover, no guided tours needed
- [ ] **UX-02**: Every page achieves top 1% design quality — composition, typography, whitespace, visual hierarchy, micro-interactions all feel premium and intentional, not AI-generated

### Core Pages (CORE)

- [ ] **CORE-01**: Dashboard — redesign visuel complet (composition KPI, session list, aside, densité, typographie, guidance tooltips)
- [ ] **CORE-02**: Wizard — redesign visuel (stepper, formulaire centré, progression, espacement, micro-interactions, field-level tooltips)
- [ ] **CORE-03**: Operator console — redesign visuel (sidebar agenda, status bar, tabs, live panel, densité opérationnelle, action tooltips)
- [ ] **CORE-04**: Hub — redesign visuel (stepper sidebar, quorum bar, checklist, états de session, step guidance tooltips)
- [ ] **CORE-05**: Post-session — redesign visuel (stepper, result cards, progression archivage, espacement sections, status tooltips)
- [ ] **CORE-06**: Settings/Admin — redesign visuel (sidenav, formulaires, KPI admin, section cards, setting explanation tooltips)

### Data Pages (DATA)

- [ ] **DATA-01**: Audit log — redesign visuel (toolbar, table density, filtres, timeline view, column header tooltips)
- [ ] **DATA-02**: Archives — redesign visuel (card/table view, filtres, pagination, états session)
- [ ] **DATA-03**: Members — redesign visuel (stats bar, member cards/table, import, rôles, action tooltips)
- [ ] **DATA-04**: Users — redesign visuel (role panel, avatar table, pagination)
- [ ] **DATA-05**: Analytics — redesign visuel (KPI cards, chart layout, responsive grid, metric tooltips)
- [ ] **DATA-06**: Meetings list — redesign visuel (session cards, status badges, actions)

### Secondary Pages (SEC)

- [ ] **SEC-01**: Login — redesign visuel (card centré, branding, champs, CTA)
- [ ] **SEC-02**: Landing — redesign visuel (hero, features, trust signals, CTA)
- [ ] **SEC-03**: Help/FAQ — redesign visuel (accordion, catégories, search)
- [ ] **SEC-04**: Email templates — redesign visuel (editor + preview grid)
- [ ] **SEC-05**: Vote (mobile) — redesign visuel (ballot, boutons, feedback, états, vote confirmation tooltips)
- [ ] **SEC-06**: Public/Projector — redesign visuel (display temps réel, résultats)
- [ ] **SEC-07**: Report/PV — redesign visuel (preview, download, timeline)
- [ ] **SEC-08**: Trust/Validate/Doc — redesign visuel (pages utilitaires, cohérence)

## v5+ Requirements (Deferred)

- **FUT-01**: AI-assisted PV minutes generation
- **FUT-02**: ClamAV virus scanning for uploaded PDFs
- **FUT-03**: Per-tenant motion templates in database
- **FUT-04**: Electronic signature upload/validation
- **FUT-05**: Votes pour collectivités territoriales

## Out of Scope

| Feature | Reason |
|---------|--------|
| New functionality | v4.2 is pure visual/UX — no new features |
| Framework migration | Vanilla stack is the identity |
| Guided tours | User explicitly rejected — use tooltips instead |
| Build tools (Tailwind, PostCSS) | No build step — raw CSS custom properties |
| New Web Components | Restyle existing, don't create new |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| UX-01, UX-02 | TBD | Pending |
| CORE-01 through CORE-06 | TBD | Pending |
| DATA-01 through DATA-06 | TBD | Pending |
| SEC-01 through SEC-08 | TBD | Pending |

**Coverage:**
- v4.2 requirements: 22 total (UX:2, CORE:6, DATA:6, SEC:8)
- Mapped to phases: 0
- Unmapped: 22 ⚠️

---
*Requirements defined: 2026-03-19*
