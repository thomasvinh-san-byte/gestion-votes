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
- ✅ **v2.2 Refonte Visuelle & Cohérence** - Phases 1-4 (shipped 2026-04-29, PR #256 mergée — partial : 5 items L01/L03/L04/L05/X01 reportés v2.3, livré L02/X02/X03/X04)
- ✅ **v2.3 Layout Refonte & UX Polish** - Phases 1-4 (PR #259 ouvert 2026-05-04, 35/35 reqs PASSED, gates manuelles A1+B1.1+D1 pending dev machine) — cockpit santé, pages éditoriales, lexique unifié, modales focus trap, + quick task TECH-01 (234 borders consolidées, 6 nouveaux tokens) — voir `.planning/milestones/v2.3-REQUIREMENTS.md`
- 🚧 **v2.4 Polish & Robustness** - Phases 1-4 (planning, 12 requirements, démarre post-merge PR #259) — cockpit declutter ≤25 boutons + persona color confinement, error observability (business_error → codes ciblés + race conditions empty-state idempotency), test infrastructure (seed-meeting helper, code-reviewer scope splits, Playwright dual-install fix), print + tech debt residuel (dompdf header + ~140 borders + ~45 shadows tokens ≥95 %)

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

### ✅ v2.3 Layout Refonte & UX Polish (Shipped — PR #259, gates pending dev machine)

**Milestone Goal:** Compléter la refonte visuelle initiée en v2.2 sur les écrans à plus haute charge émotionnelle (cockpit live opérateur, PV éditorial, dashboard, login), appliquer la convention lexicale unifiée, et résoudre le backlog UX/UI critique (modales focus trap, error messages "et maintenant?"). Test ultime : un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.

**Scope:** Couches visuelles + cohérence terminologique + UX critique. Aucune logique métier touchée. Aucune nouvelle dépendance (Fraunces déjà chargée, ag-modal existant, axe-core intégré).

**Strategy:** 4 phases, build order : cockpit (plus haute valeur) → éditorial → secondaires → lexique+UX (filet de sécurité). 1 PR par phase, base = main une fois v2.2 (PR #256) mergée.

**Milestone Success Gate — Screenshot Panel:** En fin de Phase 4, capture des screenshots avant (snapshot v2.2-merge) et après (snapshot v2.3-merge) sur 5 écrans clés : `/login`, `/dashboard`, `/operator` (mode exec), `/audit` (PV), un modal d'erreur. 3 observateurs internes notent chaque paire sur 5 dimensions (échelle 1-5) :

| Dimension | Question |
|---|---|
| Confiance | "Cette interface m'inspire confiance" |
| Compréhension | "Je comprends quoi faire sans qu'on m'explique" |
| Charge | "Je ne suis pas surchargé visuellement" |
| Action | "Je sais quelle action prendre ensuite" |
| Sérieux | "Ça a l'air légal / professionnel" |

**Critère de réussite :** médiane gagne **≥ +1 point** sur chaque dimension entre snapshot v2.2-merge et snapshot v2.3-merge. Sinon — Phase 4 ne peut pas être déclarée complete et un correctif de polish s'ajoute avant merge.

Cette gate encode explicitement le test ultime ("celui-là est plus rassurant") qui était jusqu'ici dans la goal sans être mesuré.

## Phase Details

### Phase 1: Cockpit Opérateur live

**Goal**: Refonte de la vue exécution opérateur en y intégrant une barre santé séance unique avec hiérarchie à deux niveaux (primary : Quorum + Résolution / ambient : SSE + votes restants), encapsulée dans un custom element `<ag-health-bar>` réutilisable et testable. La barre supporte un état d'anticipation "quorum à risque" et des raccourcis clavier pour les actions critiques sous stress.

**Depends on**: Nothing (first phase)
**Requirements**: COCKPIT-01, COCKPIT-02, COCKPIT-03, COCKPIT-04, COCKPIT-05, COCKPIT-06, COCKPIT-07
**Success Criteria** (what must be TRUE):
  1. La vue exécution opérateur (`/operator` en mode exec) affiche une barre santé persistante au top avec **hiérarchie à 2 niveaux** : Quorum (atteint vert / à risque warning / non-atteint rouge avec ratio) et numéro + titre tronqué de la résolution active en typo dominante (primary) ; SSE état (live/reconnecting/offline) et "Votes restants : N / Total" sur la résolution active en pill 12-13px à droite (ambient). Lisible à 1m d'écran sur le primary.
  2. Quand le quorum bascule en non-atteint pendant la séance, une bordure danger animée (pulse 1.5s, opacity max 0.6) entoure la zone vote — animation supprimée si `prefers-reduced-motion: reduce`. Quand le ratio descend sous 110 % du seuil mais reste atteint, l'indicateur Quorum passe en couleur warning (sans pulse) — état "à risque" anticipatif.
  3. Sous 768px viewport, la barre santé devient un stack vertical plutôt qu'une compression horizontale illisible.
  4. Le custom element `<ag-health-bar>` est consommé via attributs réactifs (quorum-state, quorum-ratio, sse-state, votes-remaining, motion-number, motion-title) ; tous les changements d'attribut déclenchent un re-render.
  5. Raccourcis clavier actifs sur la vue exécution : `L` lance le vote actif, `F` ferme le scrutin actif, `→` ou `N` passe à la résolution suivante, `?` affiche un overlay des raccourcis. Désactivés dans les inputs/textareas/contenteditable.
  6. Aucune régression sur les flows existants (lancer vote, fermer scrutin, passer motion) — vérifié par les tests Playwright operator-e2e.

**Plans:** 4 plans
- [x] 01.1-PLAN.md — Custom element <ag-health-bar> + stylesheet (states, pulse, responsive) — shipped 2026-04-30 (commits c337607, 8338ff8)
- [x] 01.2-PLAN.md — Keyboard shortcuts module + overlay (L/F/→/?/Escape) — shipped 2026-04-30 (commits 22f571e, d2a81c2)
- [x] 01.3-PLAN.md — Wire <ag-health-bar> + keybindings into operator.htmx.html (SSE + at-risk threshold) — shipped 2026-04-30 (commits 5d719cd, 416566e, b61854f)
- [x] 01.4-PLAN.md — Playwright E2E: health-bar states, keyboard shortcuts, regression check

### Phase 2: Pages éditoriales

**Goal**: Donner aux pages à valeur de preuve (`/audit`, `/trust`, `/archives`, `/report`) un traitement éditorial qui dégage immédiatement le sérieux légal — police serif Fraunces sur le contenu, largeur de lecture plafonnée, monospace JetBrains Mono pour les hashes/UUID.

**Depends on**: Phase 1 (custom element pattern établi) + quick task TECH-01 (consolidation shadows/borders) **livrée avant le démarrage de Phase 2**
**Requirements**: EDITORIAL-01, EDITORIAL-02, EDITORIAL-03, EDITORIAL-04, EDITORIAL-05, EDITORIAL-06, EDITORIAL-07, EDITORIAL-08, EDITORIAL-09
**Success Criteria** (what must be TRUE):
  1. Les 4 pages éditoriales (`/audit`, `/trust`, `/archives`, `/report`) wrappent leur contenu dans `.ag-editorial` avec `max-width: 720px`, `font-family: var(--font-display)` (Fraunces), line-height 1.55-1.6. Tous les enfants directs `.ag-editorial > *` sont en `text-align: left` explicite — verrouillé par test (CSS lint ou PHPUnit qui scanne les templates).
  2. Les contrôles UI (boutons, filtres, dropdowns) restent en sans-serif (Bricolage Grotesque) — la dualité serif/sans est un signal fort de "ceci est un document légal". **Audit livré** des 5 filter tabs sur `/audit` : tabs utilisés <5 % du temps déplacés dans `<details>` "Plus de filtres" (le PLAN.md nomme les tabs retenus en avant et ceux pliés).
  3. Les hashes audit, UUID de motions, codes de vote affichés utilisent `var(--font-mono)` (JetBrains Mono).
  4. Les numéros de résolution dans le PV apparaissent en pill `--radius-pill` monospace **uniquement en en-tête de section, en liste, ou en tableau** ; inline en flux serif, ils restent en mono sans pill (le pill casserait le rythme de lecture).
  5. Le hash d'intégrité du PV est affiché en bas du document avec un lien "Vérifier l'intégrité" qui ouvre un modal. Le modal commence par un préambule pédagogique en français ("Voici la preuve que ce PV n'a pas été modifié depuis le [date]. Chaque ligne ci-dessous est un sceau cryptographique reliant la précédente — modifier une seule virgule briserait la chaîne.") avant d'afficher la chaîne de hash audit_events.
  6. Sous 768px, la largeur 720px passe à 100% width avec padding latéral — pas de scroll horizontal.
  7. `@media print` actif sur les 4 pages éditoriales : contrôles UI masqués (boutons, filtres, sidebar), `page-break-inside: avoid` sur les blocs résolution/hash, en-tête répété (titre séance + date) et numéro de page en footer. Imprimé en N&B reste lisible sans dépendre du contraste couleur.
  8. Le wrapper `.ag-editorial` utilise **`display: grid`** (pas flex) pour structurer la colonne contenu (max-width 720px) et une colonne sidebar (hash d'intégrité + méta + nav interne) sur viewport ≥ 1024px ; collapse vertical sous 1024px.
  9. **0 padding/margin hardcodés** dans `public/assets/css/audit.css` après Phase 2 (et tout autre CSS touché par `.ag-editorial`) — `grep -cE "(padding|margin):\s+[0-9]+" public/assets/css/audit.css` retourne 0. Tous via tokens.

### Phase 3: Layouts secondaires

**Goal**: Simplifier le dashboard pour qu'il livre l'info principale en un coup d'œil, et alléger la page de login de son orbe animé + de son surplus marketing pour faire de la place au formulaire.

**Depends on**: Quick task TECH-01 (consolidation shadows/borders) **livrée** avant Phase 3
**Requirements**: DASHBOARD-01, DASHBOARD-02, DASHBOARD-03, DASHBOARD-04, DASHBOARD-05, DASHBOARD-06, LOGIN-01, LOGIN-02, LOGIN-03
**Success Criteria** (what must be TRUE):
  1. Le dashboard affiche au plus 3 KPI cards (au lieu de 4). Le PLAN.md de la phase nomme explicitement le KPI supprimé et justifie pourquoi il a la moindre charge décisionnelle. Le KPI déposé est intégré ailleurs (lien vers `/analytics`) — aucune information perdue.
  2. La hero card pleine largeur affiche **3 états distincts** d'imminence : *ambient* (séance dans <60min, >5min, action "Préparer"), *urgent* (séance dans <5min, accent warning, action "Démarrer maintenant"), *live* (séance en cours, accent danger pulse, action "Reprendre"). Aucune hero card si >60min.
  3. Les actions rapides (Créer séance, Importer membres, etc.) sont reléguées en bas du dashboard avec `--surface-sunken` background.
  4. **Empty state** quand aucune séance prévue ni récente (<30j) : message centré "Aucune séance prévue. Créez-en une pour commencer." + CTA primaire vers `/seances/nouvelle`. Pas d'illustration décorative.
  5. **Audit + groupement des 15 shortcut-cards** : top 5 utilisés en avant en grille principale, le reste replié derrière un disclosure "Toutes les actions" ou groupé par persona. Le PLAN.md nomme les 5 retenues et justifie leur priorité.
  6. **Layout dashboard via `display: grid`** : hero card pleine largeur + grille KPI 3 colonnes (`grid-template-columns: repeat(3, 1fr)` ou équivalent). Plus de `flex-basis` hacks pour aligner les KPI.
  7. La page `/login.html` ne contient plus l'orbe animé (suppression de `.login-orb` et de la radial-gradient associée) **ni le pattern de fond `login-brand-grid`**. Le `login-brand-glow` radial atténué peut rester comme single subtle gradient.
  8. Le panel brand login passe de "logo + tagline + 3 features" à "logo + tagline + 1 bénéfice" — ratio 50/50 ou 40/60 form-dominant.
  9. **0 padding/margin hardcodés** dans `public/assets/css/login.css` et `public/assets/css/pages.css` après Phase 3 — `grep -cE "(padding|margin):\s+[0-9]+" public/assets/css/login.css public/assets/css/pages.css` retourne 0/0. Tous via tokens.

### Phase 4: Lexique + UX critique

**Goal**: Cristalliser la convention lexicale unifiée (membre/participant/votant + confirmer/valider/verrouiller-archiver) dans le copy utilisateur, migrer les modales legacy vers `<ag-modal>` (focus trap), et enrichir les top 50 codes ErrorDictionary avec un "next-step" actionnable conformément à la critique Norman.

**Depends on**: Phases 1, 2, 3 (la convention s'applique à un copy stabilisé)
**Requirements**: LEX-01, LEX-02, MODAL-01, MODAL-02, MODAL-03, ERR-01, ERR-02, ERR-03, ERR-04
**Success Criteria** (what must be TRUE):
  1. Aucune modale active dans l'app n'utilise plus la classe legacy `.modal` — toutes migrées vers `<ag-modal>` web component qui fournit Tab + Shift+Tab + Escape natifs.
  2. Test E2E `tests/e2e/specs/modal-focus-trap.spec.js` ouvre une modale, vérifie que Escape la ferme, et que le focus retourne à l'élément qui l'a ouverte.
  3. Tous les boutons/liens qui ouvrent une `<ag-modal>` portent `aria-haspopup="dialog"` et un signifiant visuel (ellipsis, icône, ou suffixe textuel). Audit + correctifs livrés dans le même PR que MODAL-01/02.
  4. Convention "membre/participant/votant" appliquée par migration cas-par-cas sur `public/*.htmx.html`, `app/Templates/*.php`, `app/Services/ErrorDictionary.php`. Distinction sémantique respectée : "membre du conseil" reste, "membre votant" devient "votant".
  5. Top 50 codes ErrorDictionary les plus utilisés ont chacun un "next-step" actionnable (au moins une virgule + un verbe d'action en impératif ou subjonctif). Exemple validé : `'already_voted' => 'Vous avez déjà voté sur cette résolution. Pour modifier, demandez à l'opérateur d'annuler le précédent.'`.
  6. `tests/Security/UxConventionsTest.php` (nouveau) scanne ErrorDictionary et exige (a) un next-step dans chaque message des 50 codes les plus fréquents et (b) qu'aucun de ces messages ne matche les regex de phrases creuses interdites (`/réessayer\.?$/i`, `/contactez (le|l')admin/i`, `/erreur survenue/i`, `/une erreur est survenue/i`, `/veuillez réessayer plus tard/i`).
  7. **Audit prévention** des 5 codes ErrorDictionary les plus émis : le PLAN.md identifie pour chacun si l'erreur peut être prévenue par contrainte UI plutôt que rattrapée. Au moins 2 sur 5 sont marqués "prévenu en v2.3" (ou "reporté v2.4 avec rationale écrite").
  8. **Screenshot panel gate** réussi : médiane des 3 observateurs ≥ +1 point sur chaque dimension (cf. milestone gate ci-dessus).

</details>

### 🚧 v2.4 Polish & Robustness (Planning)

**Milestone Goal:** Consolider la fiabilité production post-v2.3 — éliminer les frictions toolchain identifiées (test infra dual-install, code-reviewer timeout sur 33 fichiers), refactorer les codes d'erreur génériques en codes ciblés observables (`business_error` → 3 codes spécifiques), et finir le polish cockpit pour atteindre une charge cognitive opérateur maîtrisée (≤25 boutons visibles, palette danger confinée à l'urgence).

**Scope:** Polish UI ciblé + observability + tooling. Zéro logique métier nouvelle. Sécurité backend (SEC-V2-01..03) déférée milestone v2.5 dédié. Mobile-first opérateur + animations design-system déférés v2.6+.

**Strategy:** 4 phases. P1+P2 parallélisables (zones disjointes : cockpit JS/CSS vs services PHP). P3 prérequis avant v2.5 (pentest sans friction). P4 opportuniste, peut chevaucher P1-P3.

**Source des requirements** : `.planning/v2.4-BACKLOG-PLAN.md` — tri thématique des 17 entrées backlog v2.3.

## Phase Details (v2.4)

### Phase 1: Cockpit Polish & Hygiène

**Goal**: Atteindre une charge cognitive opérateur maîtrisée — réduire de ~70 à ≤25 le nombre de boutons/éléments cliquables visibles simultanément dans le cockpit, et confiner la palette rouge danger aux états critiques uniquement (jamais décoratif sur sidebar/nav/présence).

**Depends on**: v2.3 mergée (PR #259)
**Requirements**: COCKPIT-V24-01, COCKPIT-V24-02
**Success Criteria** (what must be TRUE):
  1. Le cockpit opérateur en mode `exec` affiche **≤25 boutons / éléments cliquables visibles simultanément** sur viewport ≥1024px. Le PLAN.md identifie chaque bouton actuel (~70), propose un regroupement (panel rétractable, persona-scoped, contextual-only), et justifie quels sont conservés en avant. Audit avant/après livré.
  2. Le rouge `--color-danger` / `--color-danger-subtle` apparaît **uniquement** dans des états critiques (quorum perdu animé, vote raté, hero card live). Sidebar opérateur, nav, indicateurs de présence/connexion utilisent une palette neutre ou success. Audit grep livré dans le PLAN.md identifiant tous les call-sites actuels et leur traitement (gardé / migré / supprimé).
  3. Aucune régression sur les flows existants (lancer vote, fermer scrutin, passer motion) — vérifié par les tests Playwright `cockpit-keyboard-shortcuts.spec.js` et `critical-path-operator.spec.js`.

### Phase 2: Error Observability & Resilience

**Goal**: Le code générique `business_error` est remplacé par 3 codes spécifiques (couvrant les 3 cas d'usage documentés en 04.6-AUDIT.md), les empty-states avec rafale d'événements SSE prévient les double-render via guards d'idempotence, et `Logger::error()` est enrichi avec contexte standardisé (`request_id`, `user_id`, `tenant_id`, `error_code`, `caller`) sur tous les call-sites identifiés.

**Depends on**: v2.3 mergée (parallélisable avec P1)
**Requirements**: ERR-V24-01, ERR-V24-02, ERR-V24-03
**Success Criteria** (what must be TRUE):
  1. `business_error` reste émis < 5 % des erreurs en prod (mesure via grep logs ou dashboard). Les 3 codes spécifiques sont définis dans `app/Services/ErrorDictionary.php` avec next-step conforme à v2.3 ERR-02. Tous les callers `business_error` migrés.
  2. Test E2E reproductible avec injection synthétique de 5 events SSE en 100ms : modal d'intégrité ne re-render pas (debounce ≥250ms ou state-machine `idle | rendering | rendered`). Idem pour dashboard hero card live.
  3. `Logger::error()` accepte un context array standardisé. Les 50+ call-sites identifiés par audit grep sont migrés. Un dashboard simple (page admin ou commande CLI) affiche le **taux d'utilisation du next-step ErrorDictionary** par code — métrique pour valider Phase 4 v2.3 ERR-02 en production.

### Phase 3: Test Infrastructure

**Goal**: Toolchain sans friction — un nouveau contributeur passe d'un clone à `green tests run` en <30 min. `gsd-code-reviewer` peut auditer 50+ fichiers sans timeout. Le pattern "Explore scan anti-BEM-substring" est documenté pour éviter les faux-positifs (cf. Phase 3 v2.3 Schoger S-8).

**Depends on**: v2.3 mergée (prérequis pour milestone v2.5 sécurité — pentest sans friction Playwright)
**Requirements**: TEST-V24-01, TEST-V24-02, TEST-V24-03, TEST-V24-04, TEST-V24-05
**Success Criteria** (what must be TRUE):
  1. `tests/e2e/helpers/seed-meeting.js` est implémenté avec signature `seedMeeting({tenantId, status, motionsCount}) → meetingId` et active le test `@integration` F-4 (`modal-focus-trap.spec.js`) précédemment skippé. Test passe en CI dev.
  2. `gsd-code-reviewer` accepte `--scope=js|php|tests|all` et `--timeout-min=N` (défaut 60). Vérifié via review v2.4 sur 50+ fichiers sans timeout. Documentation à jour.
  3. Dual-install Playwright résolu : un seul `package.json` source de vérité (`tests/e2e/`), root supprimé ou stub minimal. README dual-install supprimé.
  4. `tests/e2e/README.md` documente install + browsers + auth-setup rate-limit + procédures debug. Vérifié par walkthrough fresh-clone (≤30 min jusqu'au premier test vert).
  5. `.planning/codebase/EXPLORE-PATTERNS.md` documente le pattern de scan anti-BEM-substring avec 3 anti-patterns concrets et exemple correct.

### Phase 4: Print + Tech Debt residuel

**Goal**: L'export PDF (dompdf) gagne un header répété + footer pagination sur tous les PVs. Les ~140 borders et ~45 shadows hardcoded restantes (cas BASSE confiance reportés depuis TECH-01 quick) sont migrées vers tokens ; ratio ≥95 %.

**Depends on**: v2.3 mergée (opportuniste, peut chevaucher P1-P3)
**Requirements**: TECH-V24-01, TECH-V24-02
**Success Criteria** (what must be TRUE):
  1. Génération PDF (`ProcurationPdfService` + `MeetingReportsService` si applicable) produit un header répété sur chaque page (titre séance + date) et un footer numéro de page. Vérifié visuellement sur 3 PVs longs (≥10 pages).
  2. `grep -cE "(border|box-shadow):\s+[0-9]+|#[0-9a-f]{3,6}" public/assets/css/` ratio tokens vs hardcoded ≥95 %. Liste produite des residuels et migrations livrées en atomic commits per fichier.

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Design Tokens | v2.2 | 1/1 | ✓ In PR #256 | - |
| 2. Components | v2.2 | 1/1 | ✓ In PR #256 | - |
| 3. Personas | v2.2 | 1/1 | ✓ In PR #256 | - |
| 4. Layout & Lexique (partial) | v2.2 | 1/1 | ⚠ Partial in PR #256 | - |
| 1. Cockpit Opérateur live | v2.3 | 4/4 | ✓ Complete (Playwright manual) | 2026-04-30 |
| 2. Pages éditoriales | v2.3 | 6/6 | ✓ Complete | 2026-04-30 |
| 3. Layouts secondaires | v2.3 | 5/5 | ✓ Complete | 2026-04-30 |
| 4. Lexique + UX critique | v2.3 | 6/6 | ✓ Complete (Playwright + screenshot panel manual followups) | 2026-04-30 |
| 1. Cockpit Polish & Hygiène | v2.4 | 0/0 | ○ Planning (post-merge PR #259) | - |
| 2. Error Observability | v2.4 | 0/0 | ○ Planning | - |
| 3. Test Infrastructure | v2.4 | 0/0 | ○ Planning | - |
| 4. Print + Tech Debt residuel | v2.4 | 0/0 | ○ Planning | - |
