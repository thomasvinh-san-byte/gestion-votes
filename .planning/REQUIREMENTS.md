# v10.0 Visual Identity Evolution — Requirements

## Color & Palette

- [ ] **COLOR-01**: Semantic color tokens modernized for perceptual uniformity and automatic shade derivation
- [ ] **COLOR-02**: Warm-neutral gray ramp replaces current cool-toned grays across all surfaces (bg, surface, raised)
- [ ] **COLOR-03**: Accent color (indigo) appears only at interactive elements — CTAs, active nav, focus ring, inline links
- [ ] **COLOR-04**: Derived tint/shade tokens (hover, disabled, subtle) computed from base values instead of manually maintained
- [ ] **COLOR-05**: Dark mode overrides explicitly re-declare all derived tokens to prevent stale light-mode computation

## Component Geometry

- [ ] **COMP-01**: Single --radius-base token controls all border-radius values (consolidated from current multiple values)
- [ ] **COMP-02**: Shadow vocabulary reduced to 3 named levels (sm, md, lg) replacing current proliferation
- [ ] **COMP-03**: Border colors use transparency instead of solid hex for subtle depth on any background
- [ ] **COMP-04**: Skeleton shimmer loading replaces ag-spinner on dashboard and session list pages

## Hardened Foundation

- [ ] **HARD-01**: Zero hardcoded hex values in per-page CSS files — all colors via var(--token)
- [ ] **HARD-02**: Shadow DOM component fallback hex values audited and updated to match new palette
- [ ] **HARD-03**: critical-tokens inline styles in all .htmx.html files synced with new semantic tokens
- [ ] **HARD-04**: Animatable color and opacity tokens registered for CSS transition support
- [ ] **HARD-05**: Focus ring colors in Shadow DOM components use token reference pattern instead of hardcoded rgba

## Future Requirements (deferred)

- Celebration micro-animations (vote close, PV generation)
- Sidebar attention hierarchy (dim when in workflow)
- Tabular numbers in data views
- Density mode toggle
- Variable font exploration

## Out of Scope

- Typography changes — keep Bricolage Grotesque + Fraunces + JetBrains Mono
- Layout restructuring — no HTML changes to avoid v4.2 regression pattern
- New components or page additions
- Framework migration

## Traceability

| REQ-ID | Description | Phase | Status |
|--------|-------------|-------|--------|
| COLOR-01 | Semantic tokens modernized | TBD | Pending |
| COLOR-02 | Warm-neutral gray ramp | TBD | Pending |
| COLOR-03 | Accent sparsity | TBD | Pending |
| COLOR-04 | Derived shade computation | TBD | Pending |
| COLOR-05 | Dark mode derived token sync | TBD | Pending |
| COMP-01 | Radius consolidation | TBD | Pending |
| COMP-02 | Shadow vocabulary (3 levels) | TBD | Pending |
| COMP-03 | Border alpha transparency | TBD | Pending |
| COMP-04 | Skeleton shimmer loading | TBD | Pending |
| HARD-01 | Zero hardcoded hex in page CSS | TBD | Pending |
| HARD-02 | Shadow DOM fallback audit | TBD | Pending |
| HARD-03 | critical-tokens sync | TBD | Pending |
| HARD-04 | Animatable token registration | TBD | Pending |
| HARD-05 | Focus ring token pattern | TBD | Pending |
