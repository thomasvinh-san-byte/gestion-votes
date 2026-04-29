# SUMMARY — Recherche v2.3 Layout Refonte & UX Polish

**Date :** 2026-04-29
**Mode :** Recherche inline (orchestrator) — les agents `gsd-project-researcher` ont timeout deux fois cette session sur stream idle. Recherche menée par lecture directe + grep ciblé sur le codebase + analyse des décisions design v2.2 + référencement des audits UX/UI précédents.

---

## 1. STACK — additions/changements pour v2.3

### Polices : aucune nouvelle dépendance

**Décision : on conserve `Fraunces` au lieu d'introduire Newsreader.**

- `--font-display: 'Fraunces', Georgia, serif` est déjà chargée via Google Fonts (depuis v1.x) dans plusieurs entrées HTML.
- Fraunces est utilisée 3× dans `design-system.css` (`--type-page-title-font` + 2 contextes éditoriaux ligne 1022, 1356).
- Introduire Newsreader doublonnerait pour ~zéro gain ergonomique.
- **Action v2.3 phase 2** : étendre l'usage de `--font-display` (= Fraunces) sur `/audit`, `/trust`, `/archives`, `/report` plutôt que d'introduire une nouvelle police.

### Modal focus trap : composant existant

**Décision : `<ag-modal>` web component existe déjà avec `_trapFocus` câblé.**

- `public/assets/js/components/ag-modal.js:33,72` — méthode `_trapFocus` traite Tab + Shift+Tab, gère le wrap-around sur les éléments focusables.
- L'audit v1.3 (a11y) avait migré certaines modales vers ce composant.
- **Action v2.3 phase 4** : auditer les modales restantes (legacy CSS class `.modal`) et migrer vers `<ag-modal>` web component pour bénéficier du focus trap natif. Aucune nouvelle dépendance.

### Tests d'accessibilité : axe-core + Playwright déjà intégrés

- `tests/e2e/specs/accessibility.spec.js` et `contrast-audit.spec.js` existent depuis v1.3.
- `tests/Security/PersonaIsolationTest.php` (v2.2 phase 3) a établi le pattern PHPUnit pour vérifier les contrats UI/copy.
- **Action v2.3 phase 4** : ajouter `tests/Security/UxConventionsTest.php` qui scanne `ErrorDictionary.php` pour vérifier la présence d'un "next-step" (ponctuation + verbe d'action) dans les codes les plus fréquents.

### Bilan

**Aucune nouvelle dépendance npm/composer.** Tout est faisable avec ce qui est déjà installé : Fraunces (Google Fonts), axe-core (Playwright), `<ag-modal>` (custom element), tokens design system v2.2.

---

## 2. FEATURES — table stakes vs différenciateurs par phase

### Phase 1 — Cockpit santé live opérateur (DESIGN-L01)

**Table stakes :**
- Indicateur permanent du quorum (atteint vert / non-atteint rouge) — pas seulement une notification toast comme v2.0
- Indicateur SSE déjà en place dans `operator-realtime.js` (badge `data-sse-state`)
- Compteur votants connectés (déjà calculé via SSE presence)
- Résolution active (numéro + titre tronqué)

**Différenciateur — "quorum-as-a-feeling"** :
- Quand le quorum bascule en rouge, une bordure danger animée apparaît autour de la zone vote (pas juste un badge)
- Pulse douce (1.5s, 0.6 opacity max) — respecte `prefers-reduced-motion`

**Anti-feature** : surcharger la barre santé avec >5 indicateurs. Discipline 4 indicateurs max — sinon ça redevient un panel d'avion (cf. critique Norman/Zhuo).

**Inspirations validées** : Stripe Atlas dashboards, Linear's status bar, GOV.UK status pages.

### Phase 2 — Pages éditoriales (DESIGN-L03)

**Table stakes :**
- Largeur de lecture plafonnée 720px (66 caractères, optimal pour serif)
- `var(--font-display)` (Fraunces) sur le contenu
- Bricolage Grotesque sur les contrôles UI
- JetBrains Mono pour hashes/UUID/codes

**Différenciateur :**
- Numéros de résolution en pill `--radius-pill` monospace (rappelle l'identité juridique)
- Hash d'intégrité affiché en bas du PV avec lien "Vérifier l'intégrité" (cristallise l'argument "sécurité narrative" de l'analyse Zhuo)

**Anti-feature** : bordures épaisses, fonds décoratifs, illustrations. C'est un document légal, pas une infographie.

### Phase 3 — Layouts secondaires (DESIGN-L04 + L05)

**Table stakes :**
- Dashboard : KPI réduits à 3 cards (au lieu de 4)
- Dashboard : 1 hero card si séance en cours (banner d'urgence)
- Login : suppression de l'orbe animé radial-gradient (`login.css:60`)
- Login : panel brand réduit à logo + 1 bénéfice (au lieu de 3 features)

**Différenciateur :**
- Dashboard : actions secondaires reléguées avec `--surface-sunken` (token v2.2)

### Phase 4 — Lexique + UX critique (DESIGN-X01 + UX backlog)

**Table stakes :**
- Convention "membre/participant/votant" appliquée sur le copy utilisateur (pas sur les classes/IDs/columns DB)
- Modales legacy `.modal` migrées vers `<ag-modal>` (focus trap)
- Top 50 codes ErrorDictionary enrichis avec un "next-step" actionnable

**Différenciateur :**
- Test PHPUnit `UxConventionsTest` qui scanne ErrorDictionary et exige au moins une ponctuation + un verbe d'action dans chaque message FR (filet permanent contre la régression)

---

## 3. ARCHITECTURE — intégration

### Patterns à réutiliser (rien à inventer)

- **HTMX partial updates** : déjà utilisé dans `operator.htmx.html`. La barre santé peut s'insérer dans le même flux (`hx-trigger="every 5s"` ou via SSE).
- **SSE EventBroadcaster** : déjà câblé pour quorum/votes/SSE state. La barre santé consomme les événements existants, pas d'infra nouvelle.
- **CSS Custom Properties v2.2** : tokens disponibles (`--color-success`, `--color-danger`, `--surface-sunken`, etc.). Pas d'invention de valeur.
- **`<ag-modal>`** : web component existant avec focus trap.
- **PageController** : injecte déjà `data-persona`, `%%CSP_NONCE%%`, `%%APP_VERSION%%`, `%%PERSONA_LABEL%%`.

### Nouveaux composants à introduire

- **`<ag-health-bar>`** custom element (Phase 1) — encapsule la logique des 4 indicateurs + animations + responsive collapse.
- **`<ag-editorial>`** wrapper CSS class (Phase 2) — `max-width: 720px`, `font-family: var(--font-display)`, line-height optimisée. Réutilisé sur 4 pages.

### Build order recommandé

1. **Phase 1 (cockpit)** en premier — plus haute valeur perçue, isolée à `operator.htmx.html`. Faible risque cross-page.
2. **Phase 2 (éditorial)** ensuite — touche plusieurs pages mais modifications additives (wrapper CSS).
3. **Phase 3 (layouts secondaires)** en parallèle si dispo, sinon après.
4. **Phase 4 (lexique + UX)** en dernier — filet de sécurité qui cristallise les conventions.

---

## 4. PITFALLS — pièges connus

### Cockpit santé

- **Surcharger la barre.** Discipline 4 indicateurs max — tout indicateur additionnel doit déloger un existant.
- **Animation trop chargée.** La pulse danger doit respecter `prefers-reduced-motion: reduce` — sinon migraine garantie.
- **Breakpoint mobile.** Sur < 768px, la barre 4-indicateurs horizontale doit collapse en stack vertical, pas se compresser à 12px chacun.

### Pages éditoriales

- **FOUT (Flash of Unstyled Text)** : Fraunces charge async, fallback Georgia clignote. Mitigation : `font-display: swap` (déjà en place via Google Fonts par défaut).
- **Largeur 720px sur petit écran** doit devenir 100% width sous 768px avec padding.
- **JetBrains Mono pas chargée** : à vérifier dans le `<link>` Google Fonts ; sinon ajouter.

### Layouts secondaires

- **KPI removal sans redirection.** Si un KPI disparaît, vérifier que l'info reste accessible ailleurs (lien vers `/analytics`).
- **Orbe login trop attaché à la marque.** En le retirant, valider que la brand reste reconnaissable.

### Lexique + UX

- **grep+replace destructif.** "membre" apparaît 73× — la migration doit lire le contexte ("membre du conseil" reste, "membre votant" devient "votant").
- **Focus trap sans Escape.** Le `_trapFocus` doit absolument permettre Escape pour fermer la modale — sinon l'utilisateur est piégé (a11y violation grave).

### Pitfall transverse — la perception finale

Le piège majeur de v2.3 : livrer un milestone "design" qui ne se voit pas. Test ultime : un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.

---

## 5. Décisions clés captées

| Décision | Rationale |
|---|---|
| Réutiliser Fraunces | Déjà chargée, finalité éditoriale équivalente à Newsreader |
| Pas de nouvelles deps | Tout est déjà installé (axe, ag-modal, tokens v2.2) |
| `<ag-health-bar>` custom element | Encapsulation propre, réutilisable, testable |
| Build order : cockpit → éditorial → secondaires → lexique | Plus haute valeur d'abord, filet en dernier |
| Test ErrorDictionary "next-step" | Filet permanent contre la dérive |
| Migration lexicale cas-par-cas | Sémantique juridique sensible, pas de grep+replace aveugle |

---

*Recherche conduite par orchestrator inline — agents GSD researchers indisponibles cette session sur stream idle.*
