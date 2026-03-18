# Requirements: AG-VOTE v4.0 "Clarity & Flow"

**Defined:** 2026-03-18
**Core Value:** Self-explanatory voting platform — zero training, visually impressive, legally compliant

## v4.0 Requirements

### Guided UX (GUX)

- [x] **GUX-01**: Status-aware session cards on dashboard — each card shows the ONE next action for its current lifecycle state
- [x] **GUX-02**: Contextual empty states on every container (heading + description + secondary action) replacing blank tables/lists
- [x] **GUX-03**: Disabled button explanations — tooltip or inline note explaining WHY a button is locked
- [x] **GUX-04**: ag-guide Web Component wrapping Driver.js — step tours on wizard, postsession, and members pages
- [x] **GUX-05**: ag-hint Web Component — persistent dismissible inline callout for contextual help on any element
- [x] **GUX-06**: ag-empty-state Web Component — slot-based, replaces emptyState() helper across all pages
- [x] **GUX-07**: Inline contextual help — field descriptions under labels, (?) tooltip popovers for technical terms (majorité absolue, etc.)
- [x] **GUX-08**: localStorage dismissal for all guided elements (tours, hints) so they don't repeat

### Wizard & Hub (WIZ)

- [ ] **WIZ-01**: Named-step wizard with horizontal stepper (Informations → Membres → Résolutions → Révision)
- [ ] **WIZ-02**: Autosave on field blur for all wizard steps; back navigation preserves data
- [ ] **WIZ-03**: Step 4 full review card before commit with "Modifier" link per section
- [ ] **WIZ-04**: Motion template picker in wizard step 3 (3 hardcoded templates: Approbation de comptes, Élection au conseil, Modification de règlement)
- [ ] **WIZ-05**: Progressive disclosure — "Paramètres de vote avancés" toggle in wizard step 2 reveals voting power fields
- [ ] **WIZ-06**: Session hub pre-meeting checklist with blocked-reason display ("Disponible après: résolutions ajoutées")
- [ ] **WIZ-07**: Quorum progress bar with animated fill, threshold tick marker, amber→green transition
- [ ] **WIZ-08**: Hub document status indicators per motion ("Document joint ✓" / "Aucun document")

### PDF Résolutions (PDF)

- [x] **PDF-01**: resolution_documents DB table and migration (tenant_id, meeting_id, motion_id, stored_name, mime_type, file_size, uploaded_by)
- [x] **PDF-02**: ResolutionDocumentController with upload, list, delete, and authenticated serve endpoint
- [x] **PDF-03**: Secure serve endpoint — validates auth + tenant + meeting membership, serves with correct security headers (X-Content-Type-Options, Cache-Control)
- [x] **PDF-04**: AGVOTE_UPLOAD_DIR env var replacing hardcoded /tmp/ag-vote path in all upload controllers
- [x] **PDF-05**: Docker volume mount for persistent PDF storage
- [x] **PDF-06**: FilePond drag-and-drop upload in wizard step 3 (PDF only, 10MB max)
- [x] **PDF-07**: ag-pdf-viewer Web Component with inline mode (desktop) and bottom-sheet mode (mobile)
- [x] **PDF-08**: PDF viewer wired to wizard (upload + preview), hub (doc status + preview), voter view (consultation bottom sheet)
- [x] **PDF-09**: PDF viewer uses native browser iframe (CVE-2024-4367 does not apply); PDF.js deferred to v5+ if programmatic API needed
- [x] **PDF-10**: Voter PDF consultation is read-only (no download link) in bottom-sheet overlay

### Copropriété Transformation (CPR)

- [x] **CPR-01**: UI label rename — remove "copropriété", "tantièmes", "lot" vocabulary from all user-facing strings
- [x] **CPR-02**: Remove lot field from wizard member input form (dead code — not in DB schema)
- [x] **CPR-03**: Remove openKeyModal / "Clé de répartition" stub from settings.js (no API endpoint backs it)
- [x] **CPR-04**: Preserve voting_power column, BallotsService weight calculations, and tantième CSV import alias unchanged
- [x] **CPR-05**: PHPUnit regression test for weighted vote tally correctness (before and after transformation)

### Operator Console (OPC)

- [ ] **OPC-01**: Operator console layout — status bar (session name + quorum + SSE indicator), left panel (attendance + résolutions), main panel (active vote)
- [ ] **OPC-02**: Live SSE connectivity indicator — "● En direct" (green pulse) / "⚠ Reconnexion..." (amber) / "✕ Hors ligne" (red) with colour + icon + label
- [ ] **OPC-03**: Live vote count with delta indicators ("+3 votes in last 30s" alongside absolute count)
- [ ] **OPC-04**: Contextual post-vote guidance — "Vote clôturé — Ouvrez le prochain vote ou clôturez la séance"
- [ ] **OPC-05**: End-of-agenda guidance — "Toutes les résolutions ont été traitées — Clôturer la séance"

### Voter View (VOT)

- [ ] **VOT-01**: Full-screen single-focus ballot card — all navigation and chrome hidden when a vote is open
- [ ] **VOT-02**: Vote option buttons full-width, minimum 72px height, 8px spacing between options
- [ ] **VOT-03**: Optimistic vote feedback under 50ms — instant selection visual, background server submission, rollback on error
- [ ] **VOT-04**: Waiting state — "En attente d'un vote" single line, no other content when no vote is open
- [ ] **VOT-05**: Confirmation state — "Vote enregistré ✓" for 3 seconds after vote closes
- [ ] **VOT-06**: PDF consultation via ag-pdf-viewer bottom sheet (slide-up panel, stays in voting context)

### Results & Post-Session (RES)

- [ ] **RES-01**: Trustworthy result cards — absolute numbers + percentages + threshold required + ADOPTÉ/REJETÉ verdict as largest element
- [ ] **RES-02**: Bar charts for vote breakdown (POUR/CONTRE/ABSTENTION) on result cards
- [ ] **RES-03**: Post-session stepper with completion checkmarks (Résultats → Validation → PV → Archivage)
- [ ] **RES-04**: Collapsible motion result cards (default: headline only, expand for full tally)
- [ ] **RES-05**: "X votes exprimés · Y membres présents" context footer on every result card

### Visual Polish (VIS)

- [ ] **VIS-01**: CSS @layer declaration (base, components, v4) in design-system.css — new styles cannot regress existing pages
- [ ] **VIS-02**: View Transitions API for wizard step transitions, tab switching, modal open/close (progressive enhancement)
- [ ] **VIS-03**: @starting-style entry animations for modals, toasts, and new components
- [ ] **VIS-04**: color-mix() derived tokens for all new component color variations
- [ ] **VIS-05**: Anime.js count-up animations for KPI numbers on dashboard and operator console
- [ ] **VIS-06**: PC-first layout validation — 1024px+ default; mobile voter screen verified at 375px
- [ ] **VIS-07**: Dark mode parity — every new token has a dark variant in the same commit
- [ ] **VIS-08**: Measurable done criteria enforced: transitions ≤ 200ms, CLS = 0, focus rings 3:1 contrast, zero inline style=""

## v5+ Requirements (Deferred)

### Future Features

- **FUT-01**: AI-assisted PV minutes generation
- **FUT-02**: ClamAV virus scanning of uploaded PDFs
- **FUT-03**: Motion templates stored per-tenant in database (v4.0 ships hardcoded)
- **FUT-04**: Multi-page cross-session guided tours
- **FUT-05**: Electronic signature upload/validation
- **FUT-06**: Votes pour collectivités territoriales (syndicats, communes, départements)
- **FUT-07**: Suivi budget & documents PDF pour votants

## Out of Scope

| Feature | Reason |
|---------|--------|
| Framework migration (React/Vue/Laravel) | Vanilla stack is the identity |
| Word clouds or fun result formats | Undermine legal gravity of AG |
| Voter results visible during open vote | Breaks secret ballot principle |
| Help modal / tour overlay interrupting workflow | Inline contextual help is the correct pattern |
| "Are you sure?" modal chains | Reserve modals for destructive actions only |
| Forcing account creation to vote | Token-based voter auth is correct |
| Mobile optimization for admin/operator | PC-first; mobile only for voter view |
| WebSocket migration | SSE with reconnect is the correct pattern |
| Custom PDF.js toolbar | Native browser viewer sufficient for v4.0 |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| PDF-01 through PDF-10 | Phase 25 | Complete |
| GUX-01 through GUX-08 | Phase 26 | Pending |
| CPR-01 through CPR-05 | Phase 27 | Pending |
| WIZ-01 through WIZ-08 | Phase 28 | Pending |
| OPC-01 through OPC-05 | Phase 29 | Pending |
| VOT-01 through VOT-06 | Phase 29 | Pending |
| RES-01 through RES-05 | Phase 29 | Pending |
| VIS-01 through VIS-08 | Phase 29 | Pending |

**Coverage:**
- v4.0 requirements: 55 total (GUX:8, WIZ:8, PDF:10, CPR:5, OPC:5, VOT:6, RES:5, VIS:8)
- Mapped to phases: 55
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-18*
*Last updated: 2026-03-18 — roadmap created, coverage count corrected to 55*
