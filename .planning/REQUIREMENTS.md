# Requirements: AgVote v2.3 Layout Refonte & UX Polish

**Defined:** 2026-04-29
**Core Value:** Compléter la refonte visuelle initiée en v2.2 sur les écrans à plus haute charge émotionnelle, appliquer la convention lexicale unifiée, et résoudre le backlog UX/UI critique. Test ultime : un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.

**Source des requirements :** items reportés depuis v2.2 (L01, L03, L04, L05, X01) + audit UX/UI initial (3 critiques modales, 8 a11y, 16 responsive/contrastes, 7 polish) + critique Norman/Zhuo (quorum-as-a-feeling, error → next-step) + recherche inline `.planning/research/SUMMARY.md`.

---

## v1 Requirements

### Cockpit Opérateur live (Phase 1)

- [ ] **COCKPIT-01**: Une barre santé séance unique s'affiche au top de la vue exécution opérateur, avec 4 indicateurs visuels (Quorum / SSE / Votants connectés / Résolution active), persistante pendant toute la durée de la séance.
- [ ] **COCKPIT-02**: L'indicateur Quorum affiche en permanence l'état atteint (vert) ou non-atteint (rouge), avec le ratio votants présents / quorum requis. Plus de notification toast éphémère.
- [ ] **COCKPIT-03**: Quand le quorum bascule en non-atteint pendant une séance, une bordure danger animée (pulse 1.5s, opacity max 0.6) apparaît autour de la zone vote — respecte `prefers-reduced-motion: reduce`.
- [ ] **COCKPIT-04**: La barre santé devient un stack vertical en responsive (< 768px) plutôt qu'une compression horizontale illisible.
- [ ] **COCKPIT-05**: Un nouveau custom element `<ag-health-bar>` encapsule la logique : data-attributes pour les 4 valeurs, animations CSS, responsive collapse, tests d'isolation.

### Pages éditoriales (Phase 2)

- [ ] **EDITORIAL-01**: Les pages `/audit`, `/trust`, `/archives`, `/report` adoptent un wrapper `.ag-editorial` avec `max-width: 720px`, `font-family: var(--font-display)` (Fraunces), line-height 1.55-1.6 sur le contenu.
- [ ] **EDITORIAL-02**: Les contrôles UI (boutons, filtres, dropdowns) restent en `var(--font-sans)` (Bricolage) — le serif est réservé au contenu lu.
- [ ] **EDITORIAL-03**: Les hashes/UUID/codes affichés (audit chain, vote tokens, IDs) utilisent `var(--font-mono)` (JetBrains Mono).
- [ ] **EDITORIAL-04**: Les numéros de résolution dans le PV apparaissent en pill `--radius-pill` monospace, rappelant l'identité juridique.
- [ ] **EDITORIAL-05**: Le hash d'intégrité du PV est affiché en bas du document avec un lien "Vérifier l'intégrité" actionnable.
- [ ] **EDITORIAL-06**: Sous 768px, la largeur de lecture passe à 100% avec padding latéral (pas de scroll horizontal sur petit écran).

### Layouts secondaires (Phase 3)

- [ ] **DASHBOARD-01**: Le dashboard affiche au plus 3 KPI cards (au lieu de 4 actuellement). Le KPI déposé est intégré ailleurs (lien vers `/analytics`) — aucune information perdue.
- [ ] **DASHBOARD-02**: Quand une séance est en cours ou imminente (< 1h), une hero card en pleine largeur la met en avant au-dessus des KPI.
- [ ] **DASHBOARD-03**: Les actions rapides (Créer, Importer, etc.) sont reléguées en bas du dashboard avec `--surface-sunken` pour les visuellement secondariser.
- [ ] **LOGIN-01**: La page `/login.html` supprime l'orbe animé radial-gradient (`login.css:60`).
- [ ] **LOGIN-02**: Le panel brand sur login passe de "logo + tagline + 3 features" à "logo + tagline + 1 bénéfice" — le formulaire prend plus de place.

### Lexique + UX critique (Phase 4)

- [ ] **LEX-01**: Convention "membre/participant/votant" appliquée par migration cas-par-cas (lecture du contexte) sur le copy utilisateur. Distinction sémantique : membre = inscrit, participant = présent, votant = éligible au scrutin courant.
- [ ] **LEX-02**: Convention "confirmer/valider/verrouiller-archiver" appliquée. "Approuver" banni du copy de finalisation (ambiguë juridiquement).
- [ ] **MODAL-01**: Audit des modales legacy `.modal` CSS class. Migration vers `<ag-modal>` web component pour bénéficier du focus trap natif (Tab + Shift+Tab + Escape).
- [ ] **MODAL-02**: Toutes les modales actives doivent permettre Escape pour fermer (a11y critique). Ajout d'un test E2E qui ouvre une modale et vérifie que Escape la ferme + restore le focus à l'élément précédent.
- [ ] **ERR-01**: Top 50 codes `ErrorDictionary.php` (les plus utilisés) enrichis avec un "next-step" actionnable. Exemple : `"Vous avez déjà voté sur cette résolution."` → `"Vous avez déjà voté sur cette résolution. Pour modifier, demandez à l'opérateur d'annuler le précédent."`
- [ ] **ERR-02**: Test PHPUnit `tests/Security/UxConventionsTest.php` qui scanne ErrorDictionary et exige au moins une virgule + un verbe d'action (impératif ou subjonctif) dans chaque message des 50 codes les plus utilisés. Filet permanent contre la régression.

---

## v2 Requirements (deferred)

### UX backlog v2 (post-v2.3)

- **UX-V2-01**: Animation système (transitions cross-page, skeleton loading enrichi)
- **UX-V2-02**: A/B testing visuel (différentes hiérarchies KPI sur dashboard)
- **UX-V2-03**: Mobile-first redesign de la vue opérateur (responsive ≠ mobile-native)
- **UX-V2-04**: Empty states enrichis sur toutes les pages (illustrations légères)

### Sécurité tech debt v2.1

- **SEC-V2-01**: 8 méthodes MotionRepository à `tenantId = ''` optionnel (audit des callers)
- **SEC-V2-02**: Migration progressive `field()` → `fieldFor(method, path)` pour activer F10 sur tous les forms
- **SEC-V2-03**: Hash invitation token SHA-256 → HMAC-SHA256 (forcer re-issue)

---

## Out of Scope

| Feature | Reason |
|---------|--------|
| Refonte du backend / logique métier | v2.3 est purement visuel + UX — aucune logique modifiée |
| Nouveaux écrans / fonctionnalités | UX existante stabilisée d'abord |
| Migration framework (Vue, React, Symfony) | Refactoring incrémental seulement |
| i18n complète (anglais, espagnol) | App ciblée FR uniquement |
| Mobile native (iOS / Android) | Web-first, responsive suffisant |

---

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| COCKPIT-01 | Phase 1 | Pending |
| COCKPIT-02 | Phase 1 | Pending |
| COCKPIT-03 | Phase 1 | Pending |
| COCKPIT-04 | Phase 1 | Pending |
| COCKPIT-05 | Phase 1 | Pending |
| EDITORIAL-01 | Phase 2 | Pending |
| EDITORIAL-02 | Phase 2 | Pending |
| EDITORIAL-03 | Phase 2 | Pending |
| EDITORIAL-04 | Phase 2 | Pending |
| EDITORIAL-05 | Phase 2 | Pending |
| EDITORIAL-06 | Phase 2 | Pending |
| DASHBOARD-01 | Phase 3 | Pending |
| DASHBOARD-02 | Phase 3 | Pending |
| DASHBOARD-03 | Phase 3 | Pending |
| LOGIN-01 | Phase 3 | Pending |
| LOGIN-02 | Phase 3 | Pending |
| LEX-01 | Phase 4 | Pending |
| LEX-02 | Phase 4 | Pending |
| MODAL-01 | Phase 4 | Pending |
| MODAL-02 | Phase 4 | Pending |
| ERR-01 | Phase 4 | Pending |
| ERR-02 | Phase 4 | Pending |

**Coverage:**
- v1 requirements: 22 total
- Mapped to phases: 22
- Unmapped: 0 ✓

---

*Requirements defined: 2026-04-29 — informed by .planning/research/SUMMARY.md*
*Last updated: 2026-04-29 — v2.3 milestone bootstrap*
