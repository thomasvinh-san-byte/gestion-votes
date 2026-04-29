# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- ✅ **v1.4 Regler Deferred et Dette Technique** - Phases 1-6 (shipped 2026-04-10) — see `.planning/milestones/v1.4-ROADMAP.md`
- ✅ **v1.5 Nettoyage et Refactoring Services** - Phases 1-7 (shipped 2026-04-20) — see `.planning/milestones/v1.5-ROADMAP.md`
- ✅ **v1.6 Reparation UI et Polish Fonctionnel** - Phases 1-4 (shipped 2026-04-20) — see `.planning/milestones/v1.6-ROADMAP.md`
- ✅ **v1.7 Audit Idempotence** - Phases 1-3 (shipped 2026-04-20) — see `.planning/milestones/v1.7-ROADMAP.md`
- ✅ **v1.8 Refonte UI et Coherence Visuelle** - Phases 1-5 (shipped 2026-04-20) — see `.planning/milestones/v1.8-ROADMAP.md`
- ✅ **v1.9 UX Standards & Retention** - Phases 1-5 (shipped 2026-04-21) — see `.planning/milestones/v1.9-ROADMAP.md`
- ✅ **v2.0 Operateur Live UX** - Phases 1-4 (shipped 2026-04-29) — see `.planning/milestones/v2.0-ROADMAP.md`
- ✅ **v2.1 Hardening Sécurité** - Phases 1-6 (shipped 2026-04-29) — see `.planning/milestones/v2.1-REQUIREMENTS.md` — 21 contremesures F02-F22
- 🚧 **v2.2 Refonte Visuelle & Cohérence** - Phases 1-4 (in PR #256, partial — 5 items reportés v2.3)
- 🚧 **v2.3 Layout Refonte & UX Polish** - Phases 1-4 (planning) — cockpit santé, pages éditoriales, lexique unifié, modales focus trap

## Phases

<details>
<summary>✅ v1.0 Dette Technique (Phases 1-4) - SHIPPED 2026-04-07</summary>

See `.planning/milestones/v1.0-ROADMAP.md` for full details.

</details>

<details>
<summary>✅ v1.1 Coherence UI/UX et Wiring (Phases 5-7) - SHIPPED 2026-04-08</summary>

See `.planning/milestones/v1.1-ROADMAP.md` for full details.

**Phases:** 3 (5, 6, 7)
**Plans:** 11
**Hotfixes delivered:** 3 (RateLimiter boot, nginx routing, login redesign)

</details>

<details>
<summary>✅ v1.2 Bouclage et Validation Bout-en-Bout (Phases 8-13) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.2-ROADMAP.md` for full details.

**Phases:** 6 (8-13)
**Plans:** 36
**Critical-path Playwright specs:** 23 GREEN x 3 runs zero flake
**Hotfixes delivered:** 5 (RateLimiter boot, nginx routing, login polish, cookie domain, HSTS preload)

</details>

<details>
<summary>✅ v1.3 Polish Post-MVP (Phases 14-17) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.3-ROADMAP.md` for full details.

**Phases:** 4 (14, 15, 16, 17)
**Plans:** 12 (4 + 1 + 5 + 3) — phase 15 executed inline without PLAN files
**Shipped:**
- Visual polish: toast system unifie, dark mode parity, role-specific sidebar, micro-interactions
- Cross-browser: chromium + firefox + webkit + mobile-chrome matrix (25/25 chromium, 25/25 firefox, 23/25 webkit, 21/25 mobile-chrome)
- Accessibility deep audit: 47 structural violations fixed, keyboard-nav spec (6/6), contrast audit produced (316 nodes DEFERRED to token remediation), WCAG 2.1 AA partial conformance
- Loose ends: settings loadSettings race fixed, eIDAS chip delegation fixed, Phase 12 SUMMARY audit ledger (6 findings, 3 deferred to v2)

**Deferred to v2:**
- CONTRAST-REMEDIATION (316 color-contrast nodes, design-token work)
- V2-OVERLAY-HITTEST (systematic `[hidden]`+flex overlay sweep)
- V2-TRUST-DEPLOY (trust.htmx.html auditor/assessor fixtures)
- V2-CSP-INLINE-THEME (strict CSP compatibility)

</details>

<details>
<summary>✅ v1.4 Regler Deferred et Dette Technique (Phases 1-6) — SHIPPED 2026-04-10</summary>

See `.planning/milestones/v1.4-ROADMAP.md` for full details.

**Phases:** 6 (1-6)
**Plans:** 14
**Requirements:** 24/24 satisfied
**Shipped:**
- Contrast AA remediation: 316 violations -> 0, WCAG 2.1 AA CONFORME
- Global [hidden] rule + codebase audit
- Auditor/assessor Playwright fixtures with seed endpoint
- htmx 2.0.6 upgrade with zero regressions (4 browsers)
- CSP nonce enforcement in report-only mode
- 4 controllers refactored from >500 to <300 LOC

</details>

<details>
<summary>✅ v1.5 Nettoyage et Refactoring Services (Phases 1-7) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.5-ROADMAP.md` for full details.

**Phases:** 7 (1 cleanup + 5 refactoring + 1 validation gate)
**Plans:** 9
**Requirements:** 18/18 satisfied
**Shipped:**
- Codebase cleanup: 50+ console.log removed, dead code purged, superglobals migrated
- 5 services refactored from >600 LOC to <300 LOC each
- 7 new extracted classes (SessionManager, RbacEngine, CsvImporter, XlsxImporter, ValueTranslator, ReportGenerator, RetryPolicy)
- Zero regressions confirmed (routes unchanged, unit tests green, E2E specs intact)

</details>

<details>
<summary>✅ v1.6 Reparation UI et Polish Fonctionnel (Phases 1-4) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.6-ROADMAP.md` for full details.

**Phases:** 4 (1 JS audit + 1 form modernization + 1 wizard + 1 validation gate)
**Plans:** 8
**Requirements:** 9/9 satisfied
**Shipped:**
- JS interaction audit: 8 broken selectors fixed across 21 pages
- Form layout modernization: multi-column grids on 16 pages, field classes normalized
- Wizard compaction: CSS spacing reduced for 1080p viewport fit
- Zero regressions confirmed

</details>

<details>
<summary>✅ v1.7 Audit Idempotence (Phases 1-3) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.7-ROADMAP.md` for full details.

**Phases:** 3 (1 audit + 1 backend guards + 1 frontend/tests)
**Plans:** 4
**Requirements:** 7/7 satisfied
**Shipped:**
- 73 routes audited, 13 Critique targets identified and protected
- IdempotencyGuard on all critical creation/import/email routes
- Workflow transitions idempotent (launch/close)
- HTMX X-Idempotency-Key header on all POST/PATCH
- IdempotencyGuard JSON deserialize bug fixed

</details>

<details>
<summary>✅ v1.8 Refonte UI et Coherence Visuelle (Phases 1-5) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.8-ROADMAP.md` for full details.

**Phases:** 5
**Plans:** 9
**Requirements:** 13/13 satisfied
**Shipped:**
- Palette stone->slate (beige->gris neutre moderne)
- Wizard field-input->form-input, 33 inline styles elimines, drawer CSS classes
- Version v2.0 unifiee, footer accent fixe, modales standardisees
- Hero compact, radio->select, KPI dead code supprime

</details>

<details>
<summary>✅ v1.9 UX Standards & Retention (Phases 1-5) - SHIPPED 2026-04-21</summary>

See `.planning/milestones/v1.9-ROADMAP.md` for full details.

**Phases:** 5 (typography + sidebar + feedback + jargon + validation gate)
**Plans:** 9
**Requirements:** 16/16 satisfied
**Shipped:**
- Typography: base 16px, labels normal case, header 64px, spacing 20-24px
- Sidebar: always-open 200px, pin removed, voter sees only Voter + Mon compte, 44px touch targets
- Feedback: persistent vote confirmation with timestamp, "Chargement..." labels, ag-empty-state on all pages, filter reset
- Clarity: voter jargon eliminated, admin tooltips, checkbox confirmation, export descriptions
- Validation: NAV-04 verified, PHP syntax clean, visual coherence approved

</details>

<details>
<summary>✅ v2.0 Operateur Live UX (Phases 1-4) - SHIPPED 2026-04-29</summary>

See `.planning/milestones/v2.0-ROADMAP.md` for full details.

**Phases:** 4 (Checklist + Focus Mode + Animations + Validation Gate)
**Plans:** 6
**Requirements:** 11/11 satisfied
**Audit:** PASS (cross-phase integration verified — `.planning/milestones/v2.0-MILESTONE-AUDIT.md`)
**Shipped:**
- Checklist temps reel: quorum/votes/SSE/connected voters avec alertes visuelles
- Mode Focus: vue 5-zones epuree avec toggle + persistance sessionStorage
- Animations Vote: compteurs RAF + bar transitions, respect prefers-reduced-motion
- Hotfix securite (PR #247): /setup 404 + CSRF strict (finding F1)
- CI repair (PR #248): lint-js + migrate-check verts, validate massivement ameliore

</details>

<details>
<summary>✅ v2.1 Hardening Sécurité (Phases 1-6) — SHIPPED 2026-04-29</summary>

See `.planning/milestones/v2.1-REQUIREMENTS.md` for full archive (phases moved to `.planning/milestones/v2.1-phases/`).

**Phases:** 6 (Sprint 0 finition + Vote intégrité + Périmètre/SSRF + Uploads + Headers/cookies + Tests/monitoring)
**Plans:** 22
**Requirements:** 21/21 satisfied (F02-F22)
**Shipped:**
- Sprint 0 finition (F02-F05): ClientIp + idempotence tally + audit per-member + SSE auth-first
- Vote intégrité (F06-F10): atomic consume + hash invitations + IDOR repos + resetDemo lockdown + CSRF scopé
- Périmètre & SSRF (F11-F13): UrlValidator + rate limits constant-time + AccountLockout exponential
- Uploads & contenu (F14-F16): magic bytes PDF + formula injection + dompdf hardening
- Headers/cookies (F17-F19): CSP_STRICT_MODE + SameSite=Strict default + prod-debug refusé
- Tests/monitoring (F20-F22): testsuite Security 11 tripwires + SecuritySignal + SECURITY.md

</details>

<details>
<summary>🚧 v2.2 Refonte Visuelle & Cohérence (Phases 1-4) — IN PR #256, PARTIAL</summary>

See `.planning/REQUIREMENTS.md` (will be archived to v2.2-REQUIREMENTS.md when v2.2 closes).

**Phases shipped via PR #256:** 4 (Tokens + Components + Personas + partial Layout/Lexique)
**Plans:** 4 atomic commits + planning docs
**Requirements:** 21/26 satisfied
**Reportés à v2.3:** L01 cockpit santé, L03 PV éditorial, L04 dashboard simplifié, L05 login moins marketing, X01 convention membre/participant/votant

</details>

### 🚧 v2.3 Layout Refonte & UX Polish (Planning)

**Milestone Goal:** Compléter la refonte visuelle initiée en v2.2 sur les écrans à plus haute charge émotionnelle (cockpit live opérateur, PV éditorial, dashboard, login), appliquer la convention lexicale unifiée, et résoudre le backlog UX/UI critique (modales focus trap, error messages "et maintenant?"). Test ultime : un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.

**Scope:** Couches visuelles + cohérence terminologique + UX critique. Aucune logique métier touchée. Aucune nouvelle dépendance (Fraunces déjà chargée, ag-modal existant, axe-core intégré).

**Strategy:** 4 phases, build order : cockpit (plus haute valeur) → éditorial → secondaires → lexique+UX (filet de sécurité). 1 PR par phase, base = main une fois v2.2 (PR #256) mergée.

## Phase Details

### Phase 1: Cockpit Opérateur live

**Goal**: Refonte de la vue exécution opérateur en y intégrant une barre santé séance unique avec 4 indicateurs persistants (Quorum / SSE / Votants connectés / Résolution), encapsulée dans un custom element `<ag-health-bar>` réutilisable et testable.

**Depends on**: Nothing (first phase)
**Requirements**: COCKPIT-01, COCKPIT-02, COCKPIT-03, COCKPIT-04, COCKPIT-05
**Success Criteria** (what must be TRUE):
  1. La vue exécution opérateur (`/operator` en mode exec) affiche une barre santé persistante au top, avec 4 indicateurs lisibles à 1m d'écran : Quorum atteint vert / non-atteint rouge avec ratio, SSE état (live/reconnecting/offline), nombre de votants connectés en temps réel, numéro + titre tronqué de la résolution active.
  2. Quand le quorum bascule en non-atteint pendant la séance, une bordure danger animée (pulse 1.5s, opacity max 0.6) entoure la zone vote — animation supprimée si `prefers-reduced-motion: reduce`.
  3. Sous 768px viewport, la barre santé devient un stack vertical (4 lignes) plutôt qu'une compression horizontale illisible.
  4. Le custom element `<ag-health-bar>` est consommé via `<ag-health-bar quorum-met="true" quorum-ratio="156/150" sse-state="live" voters-online="142" motion-number="3" motion-title="..."></ag-health-bar>` ; tous les attributs reactifs.
  5. Aucune régression sur les flows existants (lancer vote, fermer scrutin, passer motion) — vérifié par les tests Playwright operator-e2e.

### Phase 2: Pages éditoriales

**Goal**: Donner aux pages à valeur de preuve (`/audit`, `/trust`, `/archives`, `/report`) un traitement éditorial qui dégage immédiatement le sérieux légal — police serif Fraunces sur le contenu, largeur de lecture plafonnée, monospace JetBrains Mono pour les hashes/UUID.

**Depends on**: Phase 1 (custom element pattern établi)
**Requirements**: EDITORIAL-01, EDITORIAL-02, EDITORIAL-03, EDITORIAL-04, EDITORIAL-05, EDITORIAL-06
**Success Criteria** (what must be TRUE):
  1. Les 4 pages éditoriales (`/audit`, `/trust`, `/archives`, `/report`) wrappent leur contenu dans `.ag-editorial` avec `max-width: 720px`, `font-family: var(--font-display)` (Fraunces), line-height 1.55-1.6.
  2. Les contrôles UI (boutons, filtres, dropdowns) restent en sans-serif (Bricolage Grotesque) — la dualité serif/sans est un signal fort de "ceci est un document légal".
  3. Les hashes audit, UUID de motions, codes de vote affichés utilisent `var(--font-mono)` (JetBrains Mono).
  4. Les numéros de résolution dans le PV apparaissent en pill `--radius-pill` monospace.
  5. Le hash d'intégrité du PV est affiché en bas du document avec un lien "Vérifier l'intégrité" qui ouvre un modal montrant la chaîne de hash audit_events.
  6. Sous 768px, la largeur 720px passe à 100% width avec padding latéral — pas de scroll horizontal.

### Phase 3: Layouts secondaires

**Goal**: Simplifier le dashboard pour qu'il livre l'info principale en un coup d'œil, et alléger la page de login de son orbe animé + de son surplus marketing pour faire de la place au formulaire.

**Depends on**: Nothing (peut paralléliser avec Phase 1-2)
**Requirements**: DASHBOARD-01, DASHBOARD-02, DASHBOARD-03, LOGIN-01, LOGIN-02
**Success Criteria** (what must be TRUE):
  1. Le dashboard affiche au plus 3 KPI cards (au lieu de 4). Le 4ᵉ KPI est intégré dans la hero card ou redirigé vers `/analytics` — aucune information perdue.
  2. Quand une séance est `live` ou `scheduled` dans la prochaine heure, une hero card pleine largeur la met en avant au-dessus des KPI (avec bouton "Reprendre" / "Démarrer").
  3. Les actions rapides (Créer séance, Importer membres, etc.) sont reléguées en bas du dashboard avec `--surface-sunken` background.
  4. La page `/login.html` ne contient plus l'orbe animé (suppression de `.login-orb` et de la radial-gradient associée).
  5. Le panel brand login passe de "logo + tagline + 3 features" à "logo + tagline + 1 bénéfice" — ratio 50/50 ou 40/60 form-dominant.

### Phase 4: Lexique + UX critique

**Goal**: Cristalliser la convention lexicale unifiée (membre/participant/votant + confirmer/valider/verrouiller-archiver) dans le copy utilisateur, migrer les modales legacy vers `<ag-modal>` (focus trap), et enrichir les top 50 codes ErrorDictionary avec un "next-step" actionnable conformément à la critique Norman.

**Depends on**: Phases 1, 2, 3 (la convention s'applique à un copy stabilisé)
**Requirements**: LEX-01, LEX-02, MODAL-01, MODAL-02, ERR-01, ERR-02
**Success Criteria** (what must be TRUE):
  1. Aucune modale active dans l'app n'utilise plus la classe legacy `.modal` — toutes migrées vers `<ag-modal>` web component qui fournit Tab + Shift+Tab + Escape natifs.
  2. Test E2E `tests/e2e/specs/modal-focus-trap.spec.js` ouvre une modale, vérifie que Escape la ferme, et que le focus retourne à l'élément qui l'a ouverte.
  3. Convention "membre/participant/votant" appliquée par migration cas-par-cas sur `public/*.htmx.html`, `app/Templates/*.php`, `app/Services/ErrorDictionary.php`. Distinction sémantique respectée : "membre du conseil" reste, "membre votant" devient "votant".
  4. Top 50 codes ErrorDictionary les plus utilisés ont chacun un "next-step" actionnable (au moins une virgule + un verbe d'action en impératif ou subjonctif). Exemple validé : `'already_voted' => 'Vous avez déjà voté sur cette résolution. Pour modifier, demandez à l'opérateur d'annuler le précédent.'`.
  5. `tests/Security/UxConventionsTest.php` (nouveau) scanne ErrorDictionary et exige le next-step présent dans chaque message des 50 codes les plus fréquents — filet permanent contre la régression.

</details>


## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Design Tokens | v2.2 | 1/1 | ✓ In PR #256 | - |
| 2. Components | v2.2 | 1/1 | ✓ In PR #256 | - |
| 3. Personas | v2.2 | 1/1 | ✓ In PR #256 | - |
| 4. Layout & Lexique (partial) | v2.2 | 1/1 | ⚠ Partial in PR #256 | - |
| 1. Cockpit Opérateur live | v2.3 | 0/? | ○ Not started | - |
| 2. Pages éditoriales | v2.3 | 0/? | ○ Not started | - |
| 3. Layouts secondaires | v2.3 | 0/? | ○ Not started | - |
| 4. Lexique + UX critique | v2.3 | 0/? | ○ Not started | - |
