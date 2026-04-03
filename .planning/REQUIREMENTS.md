# v10.0 Visual Identity Evolution — Requirements

## Color & Palette

- [x] **COLOR-01**: Semantic color tokens modernized for perceptual uniformity and automatic shade derivation
- [x] **COLOR-02**: Warm-neutral gray ramp replaces current cool-toned grays across all surfaces (bg, surface, raised)
- [x] **COLOR-03**: Accent color (indigo) appears only at interactive elements — CTAs, active nav, focus ring, inline links
- [x] **COLOR-04**: Derived tint/shade tokens (hover, disabled, subtle) computed from base values instead of manually maintained
- [x] **COLOR-05**: Dark mode overrides explicitly re-declare all derived tokens to prevent stale light-mode computation

## Component Geometry

- [x] **COMP-01**: Single --radius-base token controls all border-radius values (consolidated from current multiple values)
- [x] **COMP-02**: Shadow vocabulary reduced to 3 named levels (sm, md, lg) replacing current proliferation
- [x] **COMP-03**: Border colors use transparency instead of solid hex for subtle depth on any background
- [x] **COMP-04**: Skeleton shimmer loading replaces ag-spinner on dashboard and session list pages

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
| COLOR-01 | Semantic tokens modernized | Phase 82 | Pending |
| COLOR-02 | Warm-neutral gray ramp | Phase 82 | Pending |
| COLOR-03 | Accent sparsity | Phase 82 | Pending |
| COLOR-04 | Derived shade computation | Phase 82 | Pending |
| COLOR-05 | Dark mode derived token sync | Phase 82 | Pending |
| COMP-01 | Radius consolidation | Phase 83 | Pending |
| COMP-02 | Shadow vocabulary (3 levels) | Phase 83 | Pending |
| COMP-03 | Border alpha transparency | Phase 83 | Pending |
| COMP-04 | Skeleton shimmer loading | Phase 83 | Pending |
| HARD-01 | Zero hardcoded hex in page CSS | Phase 84 | Pending |
| HARD-02 | Shadow DOM fallback audit | Phase 84 | Pending |
| HARD-03 | critical-tokens sync | Phase 84 | Pending |
| HARD-04 | Animatable token registration | Phase 84 | Pending |
| HARD-05 | Focus ring token pattern | Phase 84 | Pending |
