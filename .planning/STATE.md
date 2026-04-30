---
gsd_state_version: 1.0
milestone: v2.3
milestone_name: Layout Refonte & UX Polish
status: Executing Phase 04
stopped_at: "Plan 04.3 livré — MODAL-02 closed via tests/e2e/specs/modal-focus-trap.spec.js (commit e4f5cf6, 257 lignes, 6 tests tagged @a11y-v2.3 : Tab cycle, Shift+Tab cycle, Escape close, focus restore Escape, focus restore X button, cross-modal smoke exportsModal). Stream timeout API post-commit — orchestrator a finalisé SUMMARY 04.3 + STATE update inline. Spec syntactiquement valide (node --check exit 0), Playwright runtime déféré machine dev (followup 04.3-FOLLOWUP-1). Phase 04 progress 4/6 plans : reste 04.5 (UxConventionsTest, depends 04.4 = TOP_50_CODES list dispo dans 04.4-SUMMARY) + 04.6 (audit prévention 5 codes + milestone gate reminder)."
last_updated: "2026-04-30T11:45:00.000Z"
progress:
  total_phases: 4
  completed_phases: 3
  total_plans: 21
  completed_plans: 19
  percent: 90
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** Test ultime — un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.
**Current focus:** Phase 04 — Lexique + UX critique

## Current Position

Milestone: v2.3 Layout Refonte & UX Polish
Branch: feat/v2.3-cockpit-operateur (Phase 01 + 02 + 03 complete)
Phase: 04 (Lexique + UX critique) — EXECUTING (3/6 plans done — 04.1, 04.2, 04.4 ; 04.3 pending)
Plan: 3 of 6 (next — 04.3 MODAL-02 test E2E Playwright)

Progress: [#########.] 96% (4/4 plans of phase 01) + 6/6 plans of phase 02 + 5/5 plans of phase 03 + 3/6 plans of phase 04 — 3/4 phases of milestone v2.3 done

**Base de planning :** v2.2 entièrement mergée dans main (PR #256, commit edd7079). Tokens, components, personas, ag-modal, CopyConventionsTest tous disponibles côté code.

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

Recent decisions affecting v2.3 Phase 04:

- [v2.3 P4.4] Plan 04.4 exécuté **hors-ordre** (avant 04.3 MODAL-02) sur demande utilisateur. 04.3 reste pending. Pas de dépendance bloquante : ERR-01 (`ErrorDictionary.php`) et MODAL-02 (test E2E) touchent des fichiers disjoints. Frontmatter 04.4 listait `depends_on: [1]` (Plan 04.1 lexique migration) — respecté (token_member_mismatch préservé byte-for-byte). Plan counter STATE.md = 3/6 (04.1+04.2+04.4 done), 04.3 sera 4/6 quand livré.
- [v2.3 P4.4] Top 50 codes identifié par grep statique uniquement (`api_fail()` est le seul pattern d'émission — `ApiResponseException::fail` et `ErrorDictionary::getMessage` directs retournent 0 hit dans le scan). 4 codes émis mais absents du dictionnaire (`authentication_required`, `access_denied`, `invalid_pdf_magic`, `file_not_found`) écartés du top 50 par critère "doit exister" — bug séparé hors-scope (out-of-scope captured dans 04.4-SUMMARY).
- [v2.3 P4.4] Sur 50 codes ciblés ERR-01 : 17 déjà enrichis par le passé (zone "sections principales" du dictionnaire avec messages longs déjà conformes — ex: `meeting_not_found`, `unauthorized`, `forbidden`, `invalid_token`, `business_error`). 33 nouvellement enrichis (zone "suppléments" avec messages courts type 'X invalide.' / 'Y introuvable.' / 'Échec de Z.'). Pas de re-réécriture des messages déjà bien faits — discipline de patch minimal.
- [v2.3 P4.4] Déviation mineure scope (cohérence) : 18 codes adjacents thématiquement (hors top 50) enrichis dans le même bloc que les 33 ciblés (ex: dans bloc "File upload" cibler `upload_error`/`file_too_large`/`invalid_mime_type`/`file_read_error`/`invalid_file_type` mais aussi enrichir `invalid_csv`/`import_failed` voisins). Justification : éviter rupture de qualité de copy entre messages contigus dans le source. Coût 0, bénéfice +18 messages enrichis. Documenté dans metrics frontmatter 04.4-SUMMARY (`adjacent_codes_enriched_for_coherence: 18`).
- [v2.3 P4.4] Baseline 206 codes preserved byte-for-byte (avant: 206 paires `=>`, après: 206) — aucun code ajouté ni supprimé. Le contrat API (`ErrorDictionary::getMessage($code)` callers) est strictement intact. Strict heuristic verbes-du-plan compteur passe de 49 → 81 (+32 above threshold ≥50).
- [v2.3 P4.4] `rate_limit_exceeded` (1 émission grep, hors top 50) **non touché** : message `'Trop de requêtes. Veuillez réessayer dans quelques instants.'` contient « réessayer » mais ne matche AUCUNE des 5 regex bannies par ERR-03 (la phrase bannie est `/veuillez réessayer plus tard/i` — « plus tard » absent ici). Borderline mais hors-scope strict (top 50 only). À enrichir si remontée terrain le justifie. Documenté en out-of-scope 04.4-SUMMARY.
- [v2.3 P4.4] TOP_50_CODES list cristallisée dans 04.4-SUMMARY (table des 50 codes ranking + état avant/après). Cette liste est la source de vérité pour `tests/Security/UxConventionsTest.php` qui sera créé en Plan 04.5 — copy-paste exact de la liste sans recompter.
- [v2.3 P4.4] Atomic commit 48ffc3f conformément à CLAUDE.md (message en anglais, format `feat(04-4): description`, body bullets descriptifs). Self-check PASSED dans 04.4-SUMMARY.

 (déviation Rule 3) : sans adaptation `.hidden = false` → `.open()`, les modales legacy ne s'ouvrent plus (`<ag-modal>` ne contrôle pas `hidden` natif — il pilote son overlay interne via `open()`/`close()` qui synchronisent `aria-hidden` + animation backdrop). 6 commits atomiques (1 par fichier HTML) groupent HTML + JS associé indissociablement. Frontmatter PLAN listait 6 HTML, SUMMARY 04.2 documente les 12 fichiers (6 HTML + 6 JS) dans `affects` pour traçabilité.
- [v2.3 P4.2] `op-quorum-modal` (operator.htmx.html L1613) **conservé** : CSS class custom (`.op-quorum-modal`), pas du legacy `.modal` au sens strict. Le grep d'acceptance `grep -F 'class="modal'` (fixed-string) ne matche pas `class="op-quorum-modal"`. Le wrapper parent `<div class="op-quorum-overlay" role="dialog" aria-modal="true">` fournit déjà la sémantique a11y. Migration totale reportée v2.4 si harmonisation souhaitée (CSS sur-mesure à revoir).
- [v2.3 P4.2] Trigger `.session-menu-btn` (meetings.htmx.html) **non modifié** : il ouvre un `<ag-popover>` (menu de 4 actions), pas une `<ag-modal>` directement. Sémantiquement `aria-haspopup="menu"` est plus juste qu'`aria-haspopup="dialog"`. La modale est ouverte indirectement via `handlePopoverAction` côté JS. Pattern popover-routed documenté dans SUMMARY ; out-of-scope MODAL-03.
- [v2.3 P4.2] Triggers dynamiques (audit timeline items, trust audit rows) : `aria-haspopup="dialog"` injecté dans le rendu JS (`audit.js::renderTimeline` + `trust.js::renderAuditLog`). Suffixe `…` ajouté à la cellule action des rows trust comme signifiant visuel ; chevron-right SVG déjà présent comme signifiant sur audit timeline items. Pattern : enrichir le rendu JS quand le trigger n'a pas de markup statique HTML.
- [v2.3 P4.2] Pattern d'event listening pour reset UI sur fermeture (validate.js) : on ne peut pas wrapper `closeValidateModal` car l'utilisateur peut fermer via Escape, X header, ou backdrop click — gérés en interne par `<ag-modal>`. Solution : écouter l'événement custom `ag-modal-close` que le composant dispatch dans `close()`. Pattern non-invasif réutilisable pour toute logique de reset post-modal.
- [v2.3 P4.2] Bug Rule 1 fixé en passant : `meetings.js::openEditModal` cherchait `modal.querySelector('input[name="editMeetingType"]')` (radios) mais le markup est `<select id="editMeetingType">`. Le `querySelector` retournait null, le pré-remplissage du type de séance ne fonctionnait jamais — bug silencieux préexistant. Switch vers `typeSelect.value = typeVal`. Inclus dans commit 506655e.
- [v2.3 P4.2] Sub-classes CSS modal-suffixed conservées : `.audit-modal-row/-label/-value/-hash` (trust), `.validate-modal-warning/-checkbox` (validate), `.launch-modal-summary/-warning` (operator). Ces classes ne matchent pas `class="modal` (préfixées par `audit-`/`validate-`/`launch-`) — pas de cleanup CSS effectué (out-of-scope, risque de régression sur autres usages non audités).

- [v2.3 P4.1] Migration lexicale **cas-par-cas** appliquée à la lettre (règle d'or "si tu hésites, garde le mot original" respectée systématiquement). 89 occurrences relues dans leur contexte, 2 migrations chirurgicales : (a) operator.htmx.html L1062 placeholder `Nom du membre…` → `Nom du votant…` (alignement sur `<label>` et `aria-label` qui disaient déjà "votant"), (b) ErrorDictionary.php L227 message `token_member_mismatch` `'... ce membre.'` → `'... ce votant.'` (token de vote = scrutin actif, "votant" plus précis ; clé technique inchangée pour préserver le contrat API). 87 conservations justifiées dans 4 scratchs `.planning/phases/04-lexique-ux-critique/scratch-04.1-{operator,help,members,errordict}.txt`.
- [v2.3 P4.1] **members.htmx.html : 0 transformation copy** — par construction la page traite du registre CRUD (inscrits), `membre` est sémantiquement correct partout (18/18 conservés). Page-référence pour le concept "membre = inscrit générique".
- [v2.3 P4.1] **help.htmx.html : 0 transformation copy** — 22/22 occurrences déjà cohérentes (membre = doctrine procuration légale + CRUD registre, votant = scrutin actif déjà bien nommé). Ajout d'une section pédagogique `#vocabulaire` (acceptance criteria task 2 du plan) entre `#faqContent` et `#exports-table` : 2 listes `<dl>` définissant membre/participant/votant + confirmer/valider/verrouiller-archiver + mention bannishment `Approuver`. Pattern HTML conservateur (`<dl class="vocab-list">` semantically correct, rendu navigateur natif acceptable sans CSS additionnel).
- [v2.3 P4.1] **LEX-02 acquis par construction** sur l'ensemble du codebase v2.3 : scan transverse `grep -rnE 'Approuver' app/ public/` retourne 0 hit. Pas de dette out-of-scope à reporter v2.4. L'unique apparition du mot est désormais pédagogique dans le glossaire help.htmx.html (« Le verbe `Approuver` est volontairement banni du copy de finalisation »).
- [v2.3 P4.1] Atomic commits per fichier modifié (3 commits car members.htmx.html avait 0 diff copy — son scratch d'audit est inclus dans le commit 0d6b651 avec help.htmx.html). Commits : `8890696` (operator), `0d6b651` (help + members + glossaire), `dbdee25` (ErrorDictionary).
- [v2.3 P4.1] Anti-régression CLAUDE.md respectée : `php -l app/Services/ErrorDictionary.php` exit 0 ; `grep copropriété|syndic` retourne 0 sur les 4 fichiers ; atomic commits per file ; messages de commit en anglais format `feat(04-1): description`.

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
Stopped at: Plan 04.4 livré — ERR-01 closed (exécuté hors-ordre, 04.3 reste pending). 33 codes du top 50 émis enrichis avec next-step actionnable dans `app/Services/ErrorDictionary.php` (17 déjà enrichis par le passé + 33 nouvellement). 18 codes adjacents thématiquement enrichis pour cohérence (déviation mineure scope, coût 0). Baseline 206 codes preserved byte-for-byte. Strict heuristic 49 → 81 (+32 above threshold). 0 phrase bannie ERR-03, 0 copropriété/syndic, 0 Approuver, 04.1 token_member_mismatch préservé. Atomic commit 48ffc3f. TOP_50_CODES list cristallisée dans 04.4-SUMMARY pour consommation par 04.5 `UxConventionsTest`. SUMMARY 04.4 + REQUIREMENTS ERR-01 [x] + ROADMAP 3/6 + STATE.md decisions × 8. Phase 04 = 3/6.
Resume file: None

**Next action:** Plan 04.3 — MODAL-02 (test E2E Playwright qui ouvre une modale, vérifie Escape la ferme + restore focus). Puis Plans 04.5-04.6 : ERR-02/03 (test PHPUnit `UxConventionsTest` consommant TOP_50_CODES de 04.4-SUMMARY) + ERR-04 (audit prévention top 5 codes émis). Followups Phase 04 04.4 cumulés : (1) ajouter au dictionnaire les 4 codes émis mais absents (`authentication_required`, `access_denied`, `invalid_pdf_magic`, `file_not_found`) — quick task possible ; (2) `rate_limit_exceeded` borderline (« réessayer dans quelques instants » non banni mais faible) — enrichir si remontée terrain ; (3) codes longue traîne (~150 codes émis 0-1 fois) restent au format minimal — acceptable. Followups 04.2 cumulés : (1) tests E2E manuels des 7 modales ; (2) cleanup CSS legacy `.modal-*` ; (3) trigger `.session-menu-btn` meetings ; (4) migration `op-quorum-modal` v2.4. Followups Phase 03 cumulés : logique JS swap `.hero-card--ambient/--urgent/--live` ; capture before/after dashboard ; gap: 14px .dashboard-urgent normalisation. Followups Phases 01-02 : Playwright specs (cockpit-health-bar, cockpit-keyboard-shortcuts, critical-path-operator, modal-intégrité).
