---
gsd_state_version: 1.0
milestone: v2.3
milestone_name: Layout Refonte & UX Polish
status: Phase 02 Complete — Ready for Phase 03
stopped_at: "Plan 02.6 livré sur `feat/v2.3-cockpit-operateur` — EditorialConventionsTest (4 guard tests PHPUnit, 37 assertions) figeant EDITORIAL-01/04/05/09. Run unique 12 ms vert (budget CLAUDE.md préservé : 1 run sur 3 autorisés). Namespace Tests\\Security cohérent CopyConventionsTest + PersonaIsolationTest, suite Security déjà déclarée phpunit.xml. 4 tests : testAgEditorialChildrenNotCentered (XPath 4 pages), testIntegrityModalContainsPedagogicalPreamble (3 strings verbatim FR), testEditorialCssHasNoHardcodedSpacing (regex 4 CSS), testResolutionPillNotInsideParagraph (F-4 Schoger lock). N-3 dédup respecté : 0 testNoForbiddenWords (CopyConventionsTest couvre déjà copropriete/syndic). DOMDocument permissif (libxml NOERROR/NOWARNING + préfixe UTF-8) pour fragments HTMX. Commit 143305b. Phase 02 : 6/6 COMPLÈTE. Followup Playwright E2E modal-intégrité reporté machine dev avant /gsd:ship."
last_updated: "2026-04-30T07:43:00.000Z"
progress:
  total_phases: 4
  completed_phases: 2
  total_plans: 10
  completed_plans: 10
  percent: 100
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** Test ultime — un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.
**Current focus:** Phase 02 — Pages éditoriales

## Current Position

Milestone: v2.3 Layout Refonte & UX Polish
Branch: feat/v2.3-cockpit-operateur (Phase 01 + 02 work)
Phase: 02 (Pages éditoriales) — COMPLETE
Plan: 6 of 6 (Plans 02.1 + 02.2 + 02.3 + 02.4 + 02.5 + 02.6 done)

Progress: [##########] 100% (4/4 plans of phase 01) + 6/6 plans of phase 02 — 2/4 phases of milestone v2.3 done

**Base de planning :** v2.2 entièrement mergée dans main (PR #256, commit edd7079). Tokens, components, personas, ag-modal, CopyConventionsTest tous disponibles côté code.

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting v2.3 Phase 02:

- [v2.3 P2.6] Namespace `Tests\Security` (pas Tests\Unit, pas AgVote\Tests\Security) — alignement strict avec CopyConventionsTest + PersonaIsolationTest existants. Suite "Security" déjà déclarée dans phpunit.xml ligne 10-12. Composer.json déclare AgVote\Tests\ mais les tests historiques ne l'utilisent pas — convention figée par usage.
- [v2.3 P2.6] N-3 dédup : pas de `testNoForbiddenWordsInEditorialPages` ici. CopyConventionsTest::testNoForbiddenSyndicalTerms scanne déjà copropriété/syndic sur public/*.html, public/*.htmx.html, public/partials/*.html, app/Templates/*.php (708 assertions globales). Dupliquer le test dans EditorialConventionsTest aurait été du double maintenance pour 0 gain de couverture.
- [v2.3 P2.6] F-4 Schoger lock posé : `testResolutionPillNotInsideParagraph` (XPath `//p//*[ag-resolution-pill]` retourne 0 sur 4 pages). La règle "pill mono UNIQUEMENT en headers/lists/tables" était documentée dans editorial.css mais non testée — désormais figée. Fail immédiat si une PR future place une pill dans un paragraphe serif.
- [v2.3 P2.6] DOMDocument permissif (`LIBXML_NOERROR | LIBXML_NOWARNING`) + préfixe `<?xml encoding="UTF-8">` — les `.htmx.html` sont des fragments, pas des documents W3C complets. Trade-off assumé : on ne valide pas le HTML, on extrait juste les invariants éditoriaux via XPath.
- [v2.3 P2.6] Régex padding/margin large `(padding|margin)(-[a-z]+)?:\s+[0-9]+(\.[0-9]+)?(px|rem|em)` — couvre toutes variantes (-top/-right/-bottom/-left/-block/-inline/...). Plus large que strict besoin mais 0 faux négatif. Confirmé : 0 match sur les 4 CSS post-02.5.
- [v2.3 P2.6] Run PHPUnit unique vert au premier essai (4 tests, 37 assertions, 12 ms). Budget CLAUDE.md max 3 préservé. Le fichier était présent en working tree (artefact session antérieure) avec le bon contenu — vérifié byte-by-byte vs spec PLAN.md, pas de réécriture nécessaire.

- [v2.3 P2.5] Baseline réelle ≠ baseline plan au démarrage de session : audit.css déjà committé (7da8173), trust.css avait 24/25 substitutions en working tree pré-existantes. Continuité respectée — finalisation ligne 619 trust.css (1 ligne restante) + report.css (4 substitutions) ; ne pas re-créer un commit pour audit.css déjà committé. Result: 3 commits atomiques (audit/trust/report), pas 4 — archives.css déjà entièrement tokenisé baseline donc aucun commit créé (honnêteté > critère "4 commits").
- [v2.3 P2.5] archives.css : 0 modification nécessaire. Le fichier était déjà littéralement conforme à son commentaire de tête "All values use design-system tokens — no magic numbers". Border-left status accents (`3px solid var(--color-primary/success/warning)`) laissés tels quels : BASSE confiance TECH-01 (couleur sémantique + width non-1px hors table de mapping).
- [v2.3 P2.5] Cas spéciaux laissés sur les 4 fichiers : `padding: 0` resets en @media print (report.css 3 lignes), `padding: 0 5px` count badge archives.css ligne 107 (non matché grep, pas de token --space-1-25 standard), `box-shadow: 0 0 0 4px var(--color-primary-subtle)` focus ring report.css (BASSE confiance, focus ring pattern hors mapping TECH-01).
- [v2.3 P2.5] Valeur exotique arrondie : `0.875rem` (=14px, padding horizontal `.audit-chip` trust.css) → `var(--space-3)` (=12px) — perte 2px cosmétique mineure assumée pour la cohérence design-system. Aucune autre exotique sur les 3 fichiers modifiés.

- [v2.3 P2.4] F-5 lock Schoger respecté : pas de `position: running()` ni `@page { @top-left { content: element(...) } }` — supportés uniquement par Prince/antiword, ignorés par Chrome/Firefox/Safari à l'impression navigateur. Pragmatic fallback : `<header class="ag-editorial-print-header">` masqué hors print (déclaré `display:none` ligne 88 de editorial.css), révélé dans `@media print` via `display:block`. Apparaît en début de document, pas répété par page.
- [v2.3 P2.4] EDITORIAL-07 livré PARTIEL : numéro de page footer livré (counter(page)/counter(pages) supporté Chrome/FF/Safari), en-tête présent en début de doc uniquement (pas par page). Si répétition par page devient requise, route via dompdf (ProcurationPdfService déjà dans le codebase) en backlog v2.4 — pipeline serveur supporte CSS Paged Media complet.
- [v2.3 P2.4] `.ag-resolution-pill` retiré du `page-break-inside: avoid` (NIT N-2 du checker iter 1) : une pill inline-flex ne peut pas, par construction CSS, être coupée par un saut de page (les inlines suivent leur ligne parent). Règle redondante.
- [v2.3 P2.4] `!important` autorisé exceptionnellement dans `@media print` — pattern standard CSS Paged Media pour forcer l'override des styles écran à l'impression. Exception explicite à la règle "no `!important`" de Plan 02.1.
- [v2.3 P2.4] `.ag-integrity-footer` conservé visible en print (display: block !important) avec `border-top: 1px solid #000`, mais `.ag-integrity-footer a` (lien clickable du verify modal) masqué — inutile sur papier. `page-break-before: avoid` pour coller le hash à son PV.
- [v2.3 P2.4] Liens externes affichés en mono : `.ag-editorial a[href]:not([href^="#"]):after { content: " (" attr(href) ")" }`. Sélecteur `:not([href^="#"])` exclut les ancres internes (`#section`) inutiles sur papier.

- [v2.3 P2.3] Wrapper `.ag-editorial` appliqué en class supplémentaire sur conteneurs existants (`.container`, `.audit-page`, `.archives-main`) plutôt que via un nouveau `<div>` wrapping — minimise le diff HTML, préserve la cascade legacy `.container > .card`, évite tout risque de régression sur les sélecteurs descendants.
- [v2.3 P2.3] Audit filter triage F-2 (Schoger S-7) : `Sécurité` + `Système` déplacés dans `<details class="audit-filter-tabs__more"><summary>Plus de filtres</summary>` ; `Tous` / `Votes` / `Présences` restent visibles. Le selector JS `[data-type="..."]` matche identiquement quelle que soit la position dans le DOM, aucune adaptation côté `audit.js` requise.
- [v2.3 P2.3] F-3 décision : hydratation client (`data-events='[]'` initial sur `<ag-integrity-modal>`) plutôt que server-side `%%KEY%%` templating. Justification : `HtmlView::render()` actuel n'a pas de pattern dédié pour JSON arrays HTML-escaped en attribut ; les scripts page existants (`audit.js` / `report.js`) hydrateront via `setAttribute('data-events', JSON.stringify(events))` qui est XSS-safe par construction (DOM API escape). `Array.isArray` check côté custom element gère gracefully `[]` initial (cf 02.2).
- [v2.3 P2.3] Pas de pill `.ag-resolution-pill` insérée dans `report.htmx.html` ce cycle : la page report est un cockpit d'exports/email/preview avec le PV affiché via `<iframe id="pvFrame">` (document séparé), pas un document éditorial inline. Les pills viendront en Plan 02.5 (résolutions) ciblant le rendu PV final.
- [v2.3 P2.3] Pas de `<aside class="ag-editorial-sidebar">` ajouté sur les 4 pages : la structure recommandée par le plan était indicative. Les pages utilisent déjà des grilles natives (`.grid grid-cols-1 md:grid-cols-2`) ou des layouts optimisés (KPI grid + toolbar + table sur audit). Évaluation d'un sidebar éditorial reportée après 02.6 (Playwright) si l'UX review le demande.

- [v2.3 P2.2] `<ag-integrity-modal>` en light DOM (cohérence ag-health-bar Phase 01) — la cascade laisse passer les tokens design-system et le stylesheet companion cible directement les classes BEM.
- [v2.3 P2.2] Auto-hidden via `setAttribute('hidden', '')` dans connectedCallback (B-2 fix) — sans ce verrou le modal flasherait visible avant l'application du CSS `[hidden]`. Override via attribut `data-keep-open` réservé aux tests.
- [v2.3 P2.2] Préambule construit par concaténation `+` plutôt que template literal — évite tout conflit avec apostrophes typographiques (n'a, l'a, ci-dessous —) dans la phrase verbatim et garantit que `grep -F` matche les 3 fragments-clés EDITORIAL-05 sans escape mismatch.
- [v2.3 P2.2] Focus restoration: `_previouslyFocused` capturé dans `open()`, restauré dans `_releaseFocus()` via `close()` avec try/catch silencieux (l'élément précédent peut avoir disparu, ex: HTMX swap). Comble la régression a11y du squelette du plan.
- [v2.3 P2.2] Validation `Array.isArray(events)` ajoutée après `JSON.parse` — `JSON.parse('"abc"')` ou `JSON.parse('42')` retourneraient des non-arrays qui planteraient `events.map()`. Le squelette du plan attrapait seulement les SyntaxError.

- [v2.3 P2.1] Token `--radius-pill` absent du design-system : utilisation de `--radius-full` (= 9999px) directement sur `.ag-resolution-pill`. Le seul usage existant de `--radius-pill` dans le codebase est en fallback `var(--radius-pill, 9999px)` — pas un token réel.
- [v2.3 P2.1] Wrapper `.ag-editorial` en CSS grid (display: grid) — `display: flex` proscrit sur le wrapper par EDITORIAL-08 ; `inline-flex` autorisé pour alignement intra-cellule (`.ag-resolution-pill`).
- [v2.3 P2.1] Collapse responsive au breakpoint 1024px (max-width: 1023.98px) — sidebar passe sous le contenu, pas à droite. Breakpoint 768px ajoute padding réduit + max-width 100% sur la colonne contenu.
- [v2.3 P2.1] `box-shadow: var(--shadow-xs)` ajouté sur `.ag-resolution-pill` (non explicite dans le plan, mais cohérent avec consolidation Schoger S-2 / TECH-01 — élévation discrète).
- [v2.3 P2.1] Sélecteur `details > summary` inclus dans la typography duality (Bricolage sur contrôles) — couvre le pattern HTMX accordéon des pages audit/trust.

Recent decisions affecting v2.3 Phase 01:

- [v2.3 P1.1] `<ag-health-bar>` en light DOM (pas shadow DOM) — nécessaire pour l'héritage des tokens design-system et pour que le stylesheet companion adresse `#viewExec` dans la même cascade.
- [v2.3 P1.1] Pulse "missed" sur la zone vote (`#viewExec[data-quorum-state="missed"]`) plutôt que sur le bar lui-même — fix F-2 du plan-checker, aligné sur ROADMAP SC #2.
- [v2.3 P1.1] Substitutions de tokens documentées dans le CSS : `--color-surface` (≠ `--surface-base`), `--color-bg-subtle` (≠ `--surface-sunken`), valeur littérale `999px` (pas de `--radius-pill` dans design-system.css).
- [v2.3 P1.2] Module raccourcis = IIFE (pas ES module) — convention de `public/assets/js/pages/*` ; intégré par `<script src>` dans Plan 01.3, sans build step.
- [v2.3 P1.2] Anti-trap COCKPIT-06 : exclusion `isContentEditable` ajoutée (le handler legacy de `operator-exec.js` la manquait) ; modifier keys exclus avant tout dispatch.
- [v2.3 P1.2] Fallback chain L/F → `#opBtnToggleVote` : permet à 01.2 de fonctionner avant ET après que Plan 01.3 sépare le toggle en `#opBtnLaunchVote` / `#opBtnCloseVote`.
- [v2.3 P1.2] Overlay `?` reste accessible hors mode exec — c'est de la documentation passive ; seules les actions L/F/→/N sont gated par `_isExecMode()`.
- [v2.3 P1.3] Seuil at-risk = `c < r * 1.10` (10% buffer au-dessus du quorum requis) — formule unique dans `_computeQuorumState`, appelée uniquement depuis `quorum.updated`.
- [v2.3 P1.3] Mirror `data-quorum-state` sur `#viewExec` intégré dans `_setHb` (helper unique) — pas d'event-bus, pas de listener supplémentaire ; idempotence via `getAttribute() !== s` avant `setAttribute()`.
- [v2.3 P1.3] `window.O.fn.notifyMotionChange` défini dans `operator-realtime.js` (pas dans motions.js) — garde toutes les écritures `<ag-health-bar>` dans un seul fichier ; `operator-motions.js` ne voit que le hook public.
- [v2.3 P1.3] `#opSseIndicator` retiré entièrement (DOM + writes) — la pastille ambient `sse-state` du `<ag-health-bar>` est désormais l'unique surface d'état SSE (COCKPIT-01).
- [v2.3 P1.3] Toast `Quorum atteint !` retiré — l'indicateur persistant remplace la notif éphémère (COCKPIT-02).
- [v2.3 P1.3] Re-anchor `opPresenceBadge` sur `.op-meeting-bar-right` (avec fallbacks `#opHealthBar` puis `document.body`) — fix Rule 1 nécessaire suite à la suppression de `#opSseIndicator` qui hébergeait le badge.

Recent decisions affecting v2.2:

- [v2.2 strategy] Pyramide stricte : tokens → components → personas → layout. Une PR par étage, pas de stack.
- [v2.2 brand] Bleu République `oklch(0.45 0.180 265)` (#2c468f) — plus profond que l'ancien (0.480) et harmonisé avec le DSFR sans le copier.
- [v2.2 sémantiques] Harmonisées au brand (chroma 0.13-0.18, lightness 0.45-0.62) — pas Material Default. Vert sénat hue 165, rouge huissier désat, ocre archive hue 75, bleu instruction hue 230.
- [v2.2 surfaces light] Modern tech — neutral pur `oklch(0.985 0.001 0)` = #fbfbfb. `#ffffff` réservé aux modals/popovers.
- [v2.2 dark mode] Designé indépendamment, pas un light inversé. 5 niveaux d'élévation, hue 260, saturation -25%, lightness inversée. Aucun noir pur.
- [v2.2 personas] 6 rôles dans le spectre froid 240°-330° (admin/président/opérateur/auditeur/votant/public). Différenciation par lightness/saturation, pas par teintes opposées. Distincts des `--persona-*` historiques (sections sidebar).
- [v2.2 polices] Inter pour UI, Newsreader pour contenu éditorial (PV/audit/archives), JetBrains Mono pour hashes/UUID.
- [v2.2 emails] Hex en dur conservé (compat clients email), source de vérité = DESIGN.md table de mapping.
- [v2.2 dark detection] `prefers-color-scheme` natif via `:where()` pour spécificité 0 ; toggle JS utilisateur reste prioritaire.
- [v2.2 lexique] Convention "membre/participant/votant" + "confirmer/valider/verrouiller-archiver" appliquée Phase 4 (avec layout).

### Pending Todos

- **Avant /gsd:ship Phase 1 :** exécuter manuellement les 3 specs Playwright (cockpit-health-bar, cockpit-keyboard-shortcuts, critical-path-operator) sur machine dev — sandbox sans Playwright. Voir `.planning/phases/01-cockpit-operateur/01.4-SUMMARY.md` § Followups.
- ~~**Avant /gsd:plan-phase 2 :** exécuter quick task **TECH-01**~~ → DONE (quick 260430-86c, 28 commits, 234 borders consolidées, 6 nouveaux tokens). Voir `.planning/quick/260430-86c-consolidation-73-box-shadow-57-borders-v/260430-86c-SUMMARY.md`. Cas BASSE confiance (≈140 borders + ≈45 shadows custom) reportés dans Phase 2/3 par fichier.
- Planifier v2.3 Phase 2 (Pages éditoriales) via `/gsd:plan-phase 2` — sur base requirements amendée Schoger (EDITORIAL-01..09 dont nouveaux 08 grid + 09 cleanup hardcoded).

### Blockers/Concerns

None — main à jour, branche en avance d'1 commit (UX review). Rien à rebase.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Sceller le setup: bloquer SetupController si un admin existe et exiger CSRF | 2026-04-29 | 8c0e64a | [1-sceller-le-setup-bloquer-setupcontroller](./quick/1-sceller-le-setup-bloquer-setupcontroller/) |
| 2 | TECH-01 — Consolidation 73 box-shadow + 57 borders → tokens design-system (Schoger S-2) : 234 occurrences remplacées sur 25 fichiers, 6 nouveaux tokens (`--shadow-xs`, `--border-default/subtle/strong/dashed/focus`) | 2026-04-30 | 0ec33a2 | [260430-86c-consolidation-73-box-shadow-57-borders-v](./quick/260430-86c-consolidation-73-box-shadow-57-borders-v/) |

## Session Continuity

Last session: 2026-04-30
Stopped at: Plan 02.6 livré — tests/Security/EditorialConventionsTest.php créé (4 tests, 37 assertions, run 12 ms vert au premier essai). Budget CLAUDE.md max 3 runs préservé (1 seul run effectué). EDITORIAL-01/04/05/09 figés par tests gardiens : centrage interdit sur enfant `.ag-editorial`, préambule pédagogique verbatim FR dans ag-integrity-modal.js, 0 hardcoded spacing sur 4 CSS éditoriaux, pill mono jamais dans `<p>` serif (F-4 Schoger lock). Namespace Tests\\Security cohérent CopyConventionsTest + PersonaIsolationTest. N-3 dédup respecté (pas de doublon forbidden-words). DOMDocument permissif pour fragments HTMX. Commit 143305b. SUMMARY 02.6 écrit. **Phase 02 (Pages éditoriales) : 6/6 COMPLÈTE.** Branche feat/v2.3-cockpit-operateur prête pour PR review (Phase 01 + 02 cumulés).
Resume file: None

**Next action:** Phase 03 (Layouts secondaires — DASHBOARD-01..06 + LOGIN-01..03) à planifier via `/gsd:plan-phase 3`. Followup avant `/gsd:ship` Phase 02 : exécuter manuellement la spec Playwright modal-intégrité (à créer) sur machine dev (sandbox sans Playwright). Followups Phase 1 cumulent : cockpit-health-bar, cockpit-keyboard-shortcuts, critical-path-operator + nouveau modal-intégrité Phase 2.
