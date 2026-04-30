---
gsd_state_version: 1.0
milestone: v2.3
milestone_name: Layout Refonte & UX Polish
status: Phase 03 COMPLETE
stopped_at: "Plan 03.5 livré — LOGIN-03 (cleanup padding/margin hardcodés) finalisé sur login.css (2 hardcodes baseline → 0, déjà committé 1195cbe en début de session) et pages.css (60 hardcodes baseline → 0, commit 723123d). Token usage : 36 × var(--space-*) login.css + 76 × var(--space-*) pages.css. Atomic commits per fichier respectés. Arrondis cosmétiques documentés (5 cas) : 14px/0.875rem → space-3 (12px, -2px) sur 6 occurrences pages.css cohérent décision Plan 02.5, 0.4rem → space-1-5 (-0.4px), 0.35rem → space-1-5 (+0.4px), 0.15rem → space-0-5 (-0.4px), 22px → space-5 (-2px login.css), 52px → space-14 (+4px login.css préserve zone clic field-eye). Cas spéciaux préservés (0, auto, em hors padding/margin). Bonus TECH-01 : aucune substitution shadow/border supplémentaire — déjà migrées par TECH-01 quick task et Plan 03.4. gap: 14px dashboard-urgent conservé (hors scope plan, follow-up backlog). Phase 03 COMPLETE 5/5 plans, milestone v2.3 progress = 3/4 phases."
last_updated: "2026-04-30T11:15:00.000Z"
progress:
  total_phases: 4
  completed_phases: 3
  total_plans: 15
  completed_plans: 14
  percent: 93
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** Test ultime — un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.
**Current focus:** Phase 03 — Layouts secondaires

## Current Position

Milestone: v2.3 Layout Refonte & UX Polish
Branch: feat/v2.3-cockpit-operateur (Phase 01 + 02 + 03 complete)
Phase: 03 (Layouts secondaires) — COMPLETE
Plan: 5 of 5 done — Phase 04 (Lexique + UX critique) ready to plan

Progress: [#########.] 93% (4/4 plans of phase 01) + 6/6 plans of phase 02 + 5/5 plans of phase 03 — 3/4 phases of milestone v2.3 done

**Base de planning :** v2.2 entièrement mergée dans main (PR #256, commit edd7079). Tokens, components, personas, ag-modal, CopyConventionsTest tous disponibles côté code.

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting v2.3 Phase 03:

- [v2.3 P3.5] Arrondi cosmétique 14px/0.875rem → var(--space-3) (12px) appliqué 6× dans pages.css (.dashboard-urgent padding, .section-header margin/padding, .tab-toolbar margin, .meeting-card-body × 2, .meeting-card-header) — perte -2px assumée pour cohérence design-system, alignée sur la décision Plan 02.5 (audit-chip trust.css). Le commentaire CSS "wireframe density 14px" ligne 1347 perd 2px → cohérence > densité pixel-perfect.
- [v2.3 P3.5] Sub-rem exotiques (0.4rem, 0.35rem, 0.15rem) toutes mappées sur tokens existants (space-1-5 ou space-0-5) — différences < 0.5px imperceptibles. Tokens space-1-25 / space-1-625 / etc. n'existent pas dans design-system, ne pas en créer pour 1 occurrence chacune.
- [v2.3 P3.5] Bonus TECH-01 (shadow/border substitutions) NON effectué dans Plan 03.5 : audit a montré que les borders/shadows de pages.css et login.css étaient déjà majoritairement migrées (TECH-01 quick task 0ec33a2 + Plan 03.4 cleanup). Restent uniquement quelques borders 2px custom (.vote-live-panel, .create-card, .file-drop-zone, .dashboard-urgent) classées BASSE confiance dans l'inventaire TECH-01 — pas de gain trivial à capter, scope respecté.
- [v2.3 P3.5] gap: 14px (.dashboard-urgent) conservé tel quel : `gap` n'entre pas dans la portée du plan (regex padding|margin uniquement). Followup possible dans une dette future si normalisation `gap` souhaitée.
- [v2.3 P3.5] Atomic commits per file respectés : 1195cbe (login.css, +2/-2) + 723123d (pages.css, +61/-61). Phase 03 finalisée (5/5 plans). LOGIN-03 closed.

- [v2.3 P3.3] Cas 2 retenu (Rule 4 spec-vs-reality mismatch) : audit `grep -nE 'quick-action|action-rapide|actions-bar|quick-actions|class="actions"|Actions rapides|Raccourcis|Actions principales' public/dashboard.htmx.html` retourne 0 match. Aucun bloc "actions rapides" distinct n'existe — les actions rapides historiques sont les 3 shortcut-cards de `<aside class="dashboard-aside">` (titre "Accès rapides"). DASHBOARD-03 livré via background swap `var(--color-surface)` → `var(--color-bg-subtle)` sur l'aside (intention Schoger "secondariser visuellement" préservée). Pas de déplacement DOM. Pattern Rule 4 zero-DOM-mutation cohérent avec Plan 03.2.
- [v2.3 P3.3] Token `--color-bg-subtle` retenu (pas `--surface-sunken`) : seul candidat existant dans design-system.css (ligne 328 light + 656/808 dark). Utilisé 13× dans pages.css comme token sunken canonique. `--surface-sunken` n'existe pas comme nom littéral. Pas de fallback inline — token garanti défini par `:root` et `[data-theme="dark"]`.
- [v2.3 P3.3] Path CSS adapté (déjà documenté Plan 03.1) : le PLAN ciblait `public/assets/css/dashboard.css` inexistant, application transparente sur `public/assets/css/pages.css`.
- [v2.3 P3.3] Commit `e36b579` déjà committé en début de session (artefact session antérieure) — vérifié byte-by-byte vs spec task_specifics : conforme (Cas 2, message Cas 2 standard, diff 12/3, pas de `!important`, token correct). Pas de réécriture nécessaire.
- [v2.3 P3.3] Hiérarchie visuelle finale dashboard verrouillée : hero card (DASHBOARD-02) > KPI 3 cards (DASHBOARD-01/06) > sessions + empty state (DASHBOARD-04) > [aside relégué via sunken — DASHBOARD-03]. Aside reste à droite (grid 1fr 280px desktop) ou sous le contenu (responsive ≤1024px) — la "relegation" est visuelle, pas spatiale.

- [v2.3 P3.4] Glow atténué (judgment call autorisé par `<product_decision_locked>` LOGIN-01) : `opacity` 0.18→0.12 + suppression `animation: brand-glow-drift` + cleanup des `@keyframes brand-glow-drift` devenues orphelines + règle `@media (prefers-reduced-motion: reduce)` qui désactivait l'animation supprimée. Le glow reste fonctionnel comme single subtle gradient (≠ pattern), conformément à l'autorisation explicite du plan.
- [v2.3 P3.4] Commit unique `ef217e2` plutôt que scission LOGIN-01 / LOGIN-02 : les deux requirements modifient le même bloc HTML (panneau brand) avec un diff total de 45 lignes deletes + 3 inserts. Atomicité préférée à découpage artificiel.
- [v2.3 P3.4] Ratio `grid-template-columns: 2fr 3fr` (40% brand / 60% form) préservé tel quel — la baseline n'avait pas dérivé vers 3fr 2fr (brand-dominant). F4 fix verrouillé par construction conforme ROADMAP §8 "form-dominant".
- [v2.3 P3.4] Bénéfice retenu = "Vote sécurisé" verbatim (le 2e `<li>` historique). Libellé `<strong>Vote sécurisé</strong> <span>Token unique, chiffrement, anti-rejeu</span>` non modifié — fidélité au product_decision_locked LOGIN-02.

- [v2.3 P3.1] B1 lock posé : `.hero-card--live` utilise `--color-danger-subtle/border` (PAS `--color-success`). Justification REQUIREMENTS DASHBOARD-02 verbatim : une séance en cours = action en flux irréversible = signal de criticité opérateur, pas un signal de succès. Anti-regression doc inline dans le CSS.
- [v2.3 P3.1] B2 fix : étendre `<ag-empty-state>` avec `icon="none"` (3 lignes patch chirurgical autour de `if (icon !== 'none') { ... emit svg ... }`) plutôt qu'inventer une classe CSS `.ag-empty-state`. Préserve les 5 usages historiques (meetings/members/votes/archives/generic) intacts. JSDoc enrichie avec mention DASHBOARD-04.
- [v2.3 P3.1] F2 décision : `DashboardController::index()` est un endpoint JSON REST (`api_ok($data)` ligne 140), pas un renderer de templates HTML. L'empty state s'active 100% côté client dans `dashboard.js` quand `meetings.length === 0`. Markup spec EDITORIAL-04 vit dans un `<template id="dashboardEmptyState">` du HTML (source de vérité du texte FR + greppable + a11y safe car `<template>` n'est pas rendu).
- [v2.3 P3.1] Pattern `<template>` + `cloneNode(true)` hydration : remplace l'ancienne approche `prochaines.innerHTML = '<ag-empty-state icon="meetings" ...>'` inlinée dans dashboard.js. Source de vérité du texte FR migrée du JS au HTML — meilleure ergonomie verifiers + i18n future.
- [v2.3 P3.1] F1 KPI déposé "intégré ailleurs" : lien discret `<a href="/hub" class="link-muted">Voir aussi : convocations en attente</a>` en footer KPI (DASHBOARD-01). Préserve l'accès à la donnée Convocations sans la mettre au même niveau visuel que les 3 KPI décisionnels (AG à venir / En cours / PV à envoyer).
- [v2.3 P3.1] F3 CTA strings exact : pour résoudre la friction grep UTF-8 vs entités HTML héritées du fichier (`Pr&eacute;parer la s&eacute;ance`), les 3 CTA "Préparer la séance" / "Démarrer maintenant" / "Reprendre" sont placés dans (a) un commentaire HTML descriptif littéral et (b) le payload de `data-cta-ambient/-urgent/-live`. Le `data-*` est décodé côté JS via `dataset` (preserves entities-as-text → UTF-8 string).
- [v2.3 P3.1] Path CSS adapté : le plan référence `public/assets/css/dashboard.css` qui n'existe pas — les styles dashboard vivent dans `pages.css` (vérifié par grep `kpi-row|dashboard-kpis|kpi-card-wrapper` qui ne matche que `pages.css`). Adaptation transparente sans déviation Rule.
- [v2.3 P3.1] Hero card placée APRÈS `.dashboard-urgent` (banner urgent existant) et AVANT les KPI. `[hidden]` par défaut + `aria-labelledby="heroCardTitle"`. Le swap de modificateur (`--ambient` ↔ `--urgent` ↔ `--live`) sera implémenté dans un plan ultérieur — pour l'instant le markup structurel est posé.
- [v2.3 P3.1] Tâche 1 (`2d5e2dc`) déjà committée en début de session — vérifié byte-by-byte vs spec PLAN.md, conforme. Pas de réécriture, pas de doublon.

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
Stopped at: Plan 03.5 livré — Phase 03 COMPLETE (5/5 plans). LOGIN-03 closed : login.css (commit 1195cbe, 2 hardcodes → 0, 36 × var(--space-*)) + pages.css (commit 723123d, 60 hardcodes → 0, 76 × var(--space-*)) entièrement migrés sur tokens design-system. 5 arrondis cosmétiques documentés dans SUMMARY (14px/0.875rem → space-3, 0.4rem → space-1-5, 0.35rem → space-1-5, 0.15rem → space-0-5, 22px/52px sur login.css). Bonus TECH-01 non effectué (déjà majoritairement fait par TECH-01 quick task + Plan 03.4 — scope respecté). Atomic commits per file. SUMMARY 03.5 créé avec self-check PASSED. STATE + ROADMAP mis à jour : Phase 03 = 5/5, milestone v2.3 = 3/4 phases done (93%).
Resume file: None

**Next action:** Phase 04 (Lexique + UX critique) à planifier via `/gsd:plan-phase 4` — sur base requirements LEX-01..02, MODAL-01..03, ERR-01..04 (8-9 requirements selon découpage final). Followups Phase 03 cumulés : logique JS swap `.hero-card--ambient/--urgent/--live` selon imminence (héritée 03.1, hero card reste `[hidden]`) ; capture before/after du dashboard avec aside sunken — Schoger test ultime (héritée 03.3, manuelle) ; gap: 14px .dashboard-urgent normalisation (héritée 03.5, optionnelle). Followups Phases 01-02 cumulent : cockpit-health-bar, cockpit-keyboard-shortcuts, critical-path-operator + modal-intégrité Playwright spec.
