# Requirements: AG-VOTE v4.1 "Design Excellence"

**Defined:** 2026-03-19
**Core Value:** Every page looks professionally designed — cohesive, premium, trustworthy

## v4.1 Requirements

### Token Foundation (TKN)

- [ ] **TKN-01**: design-system.css :root block reduced from 265+ to ~100 semantic tokens organized as primitive → semantic → component
- [ ] **TKN-02**: Shadow system with 5+ named levels (xs/sm/md/lg/xl) applied semantically — cards use sm, modals use lg, tooltips use md
- [ ] **TKN-03**: Base UI font size changed from 16px to 14px for labels/chrome; body reading text stays 16px
- [ ] **TKN-04**: Typography weight hierarchy enforced — headings at 700, section titles at 600, body at 400, no 500-vs-400 ambiguity
- [ ] **TKN-05**: Spacing scale uses 8px base with semantic aliases (--space-section: 48px, --space-card: 24px, --space-field: 16px)
- [ ] **TKN-06**: Border-radius semantic tokens (--radius-sm: 4px for badges, --radius: 8px for inputs, --radius-lg: 12px for cards, --radius-xl: 16px for modals)
- [ ] **TKN-07**: Dark mode tokens derive from light via color-mix() or oklch — not hand-coded hex duplicates
- [ ] **TKN-08**: Zero hardcoded hex/rgb values in any page CSS file — all reference design-system tokens

### Component Refresh (CMP)

- [ ] **CMP-01**: Buttons — 36px default height, consistent padding/radius/weight across primary/secondary/ghost/danger variants
- [ ] **CMP-02**: Cards — 24px padding minimum, shadow-sm default, shadow-md on hover with subtle lift, border-radius-lg
- [ ] **CMP-03**: Tables — 48px row height, sticky 40px header, right-aligned numbers in monospace, hover state
- [ ] **CMP-04**: Form inputs — consistent height (36px), proper focus ring, error state with border-color change, label at 14px/600
- [ ] **CMP-05**: Modals — centered with shadow-xl, proper backdrop opacity, header/content/footer sections, close button
- [ ] **CMP-06**: Toasts — left-border accent via inset box-shadow, 356px width, slide-in animation
- [ ] **CMP-07**: Badges — pill shape (rounded-full), semantic color variants (success/warning/danger/info), 12px font
- [ ] **CMP-08**: Steppers — proper circle size, connector lines, active/done/pending states with color differentiation

### Page Layout (LAY)

- [ ] **LAY-01**: Dashboard — 4-column KPI grid, session cards as list, max-width 1200px content, quick-actions aside
- [ ] **LAY-02**: Wizard — centered 680px track, sticky footer nav with space-between, fields max-width 480px
- [ ] **LAY-03**: Operator console — 280px agenda sidebar + fluid main, fixed status bar + tab nav, CSS grid 3-row layout
- [ ] **LAY-04**: Data tables (audit, archives, members, users) — proper column alignment, sticky header, toolbar + pagination bars
- [ ] **LAY-05**: Settings/Admin — 220px left sidenav, 720px content column, section cards with per-section save
- [ ] **LAY-06**: Mobile voter — 100dvh, 72px vote buttons, safe-area padding, clamp() fluid typography
- [ ] **LAY-07**: Hub — sidebar stepper + main content, quorum bar prominent, checklist with proper spacing
- [ ] **LAY-08**: Post-session — stepper with checkmarks, collapsible result cards, proper section spacing
- [ ] **LAY-09**: Analytics/Statistics — chart area + KPI cards, proper responsive grid
- [ ] **LAY-10**: Help/FAQ — accordion with proper padding, search if applicable
- [ ] **LAY-11**: Email templates — editor layout with preview panel
- [ ] **LAY-12**: Meetings list — card or table view with proper density and status badges

### Quality Assurance (QA)

- [ ] **QA-01**: Every page passes the "6 AI anti-patterns" check — no uniform shadows, no uniform radius, spatial hierarchy present, color used for signal not decoration, weight contrast visible, hover has transform not just color
- [ ] **QA-02**: Background 3-layer stack (bg → surface → raised) applied consistently on every page
- [ ] **QA-03**: Fraunces display font used exactly once per page (page title only) — never for section headings
- [ ] **QA-04**: Dark mode visual parity — every page looks intentionally designed in dark, not just inverted
- [ ] **QA-05**: All transitions ≤ 200ms, focus rings ≥ 3:1 contrast, zero inline style="" in production HTML

## v5+ Requirements (Deferred)

- **FUT-01**: AI-assisted PV minutes generation
- **FUT-02**: ClamAV virus scanning for uploaded PDFs
- **FUT-03**: Per-tenant motion templates in database
- **FUT-04**: Electronic signature upload/validation
- **FUT-05**: Votes pour collectivités territoriales
- **FUT-06**: oklch relative color syntax (requires Chrome 119+ / Safari 16.4+)

## Out of Scope

| Feature | Reason |
|---------|--------|
| New functionality | v4.1 is pure visual — no new features |
| Framework migration | Vanilla stack is the identity |
| Build tools (Tailwind, PostCSS) | No build step — raw CSS custom properties |
| New Web Components | Use existing 23 components, just restyle them |
| Typography font changes | Keep Bricolage Grotesque + Fraunces + JetBrains Mono |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| TKN-01 through TKN-08 | Phase 30 | Pending |
| CMP-01 through CMP-08 | Phase 31 | Pending |
| LAY-01 through LAY-12 | Phase 32-33 | Pending |
| QA-01 through QA-05 | Phase 34 | Pending |

**Coverage:**
- v4.1 requirements: 33 total (TKN:8, CMP:8, LAY:12, QA:5)
- Mapped to phases: 33
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-19*
*Last updated: 2026-03-19 after research synthesis*
