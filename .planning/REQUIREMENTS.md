# Requirements: AgVote v2.3 Layout Refonte & UX Polish

**Defined:** 2026-04-29
**Core Value:** Compléter la refonte visuelle initiée en v2.2 sur les écrans à plus haute charge émotionnelle, appliquer la convention lexicale unifiée, et résoudre le backlog UX/UI critique. Test ultime : un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.

**Source des requirements :** items reportés depuis v2.2 (L01, L03, L04, L05, X01) + audit UX/UI initial (3 critiques modales, 8 a11y, 16 responsive/contrastes, 7 polish) + critique Norman/Zhuo (quorum-as-a-feeling, error → next-step) + recherche inline `.planning/research/SUMMARY.md` + revue UX 2026-04-29 (`.planning/v2.3-UX-REVIEW.md` — hiérarchie cockpit, prévention quorum, raccourcis clavier, print, empty state, prévention erreurs, modal triggers) + revue UX 2026-04-30 (`.planning/v2.3-UX-REVIEW-SCHOGER.md` — clarté visuelle Refactoring UI : text-align lock, CSS grid pattern, audit tabs declutter, login pattern, dashboard shortcut-cards, hardcoded values cleanup).

---

## v1 Requirements

### Cockpit Opérateur live (Phase 1)

- [x] **COCKPIT-01**: Une barre santé séance unique s'affiche au top de la vue exécution opérateur, persistante pendant toute la durée de la séance, avec **deux niveaux hiérarchiques** :
  - *Primary* (typo dominante, action-relevant) : Quorum + Résolution active
  - *Ambient* (pill 12-13px, télémétrie système) : SSE state + Votants connectés
  L'œil de l'opérateur sous stress doit cibler le primary sans concurrence visuelle de l'ambient.
- [x] **COCKPIT-02**: L'indicateur Quorum (primary) affiche en permanence l'état atteint (vert) ou non-atteint (rouge), avec le ratio votants présents / quorum requis. **L'indicateur "personnes" principal mesure les votes restants sur la résolution active** (`Votes restants : 23 / 142`), répondant à la question opérationnelle de l'opérateur — pas une métrique de connectivité technique. Plus de notification toast éphémère.
- [x] **COCKPIT-03**: Quand le quorum bascule en non-atteint pendant une séance, une bordure danger animée (pulse 1.5s, opacity max 0.6) apparaît autour de la zone vote — respecte `prefers-reduced-motion: reduce`.
- [x] **COCKPIT-04**: La barre santé devient un stack vertical en responsive (< 768px) plutôt qu'une compression horizontale illisible.
- [x] **COCKPIT-05**: Un nouveau custom element `<ag-health-bar>` encapsule la logique : data-attributes pour les valeurs, animations CSS, responsive collapse, tests d'isolation.
- [x] **COCKPIT-06**: Raccourcis clavier sur la vue exécution opérateur (sous stress, le clavier est plus rapide que la souris) : `L` lance le vote actif, `F` ferme le scrutin actif, `→` ou `N` passe à la résolution suivante, `?` affiche un overlay de la liste des raccourcis. Ne s'activent pas dans les inputs/textareas/contenteditable. Indication visuelle discrète sur les boutons concernés (tooltip avec la touche).
- [x] **COCKPIT-07**: État intermédiaire **"quorum à risque"** affiché quand le ratio descend sous 110 % du seuil mais reste atteint. Couleur warning douce (jamais rouge — le quorum est encore atteint), pas de pulse. Bascule visuelle anticipée → l'opérateur a 30s pour mobiliser au lieu de 0. Prévention > détection > récupération (Norman).

### Pages éditoriales (Phase 2)

- [ ] **EDITORIAL-01**: Les pages `/audit`, `/trust`, `/archives`, `/report` adoptent un wrapper `.ag-editorial` avec `max-width: 720px`, `font-family: var(--font-display)` (Fraunces), line-height 1.55-1.6 sur le contenu. **Le wrapper `.ag-editorial > *` est verrouillé en `text-align: left` explicite** — un test PHPUnit (ou CSS lint) interdit `text-align: center` sur tout enfant direct de `.ag-editorial`. *Refactoring UI* : centrer du long-form text est un anti-pattern de lisibilité, ce verrou prévient la régression "pour rendre joli".
- [ ] **EDITORIAL-02**: Les contrôles UI (boutons, filtres, dropdowns) restent en `var(--font-sans)` (Bricolage) — le serif est réservé au contenu lu. **Sur `/audit`, audit obligatoire des 5 filter tabs actuels** : si 2-3 tabs sont utilisés <5 % du temps (mesure via logs ou jugement produit), les déplacer dans `<details>` "Plus de filtres" (Schoger Ch.10 : *"Every tab is a tax"*). Le contenu prend la lumière, pas la chrome.
- [ ] **EDITORIAL-03**: Les hashes/UUID/codes affichés (audit chain, vote tokens, IDs) utilisent `var(--font-mono)` (JetBrains Mono).
- [ ] **EDITORIAL-04**: Les numéros de résolution dans le PV apparaissent en pill `--radius-pill` monospace **uniquement en en-tête de section, en liste, ou en tableau** (jamais inline dans un paragraphe serif — le pill casse alors le rythme de lecture). Inline en flux, le numéro reste en mono sans pill.
- [ ] **EDITORIAL-05**: Le hash d'intégrité du PV est affiché en bas du document avec un lien "Vérifier l'intégrité" actionnable. Le modal d'ouverture **doit commencer par un préambule pédagogique en français** avant la chaîne `audit_events` (sinon on montre du jargon — l'inverse de "rassurant"). Texte de référence : *"Voici la preuve que ce PV n'a pas été modifié depuis le [date]. Chaque ligne ci-dessous est un sceau cryptographique reliant la précédente — modifier une seule virgule briserait la chaîne."*
- [ ] **EDITORIAL-06**: Sous 768px, la largeur de lecture passe à 100% avec padding latéral (pas de scroll horizontal sur petit écran).
- [ ] **EDITORIAL-07**: Styles `@media print` sur les pages éditoriales (`/audit`, `/trust`, `/archives`, `/report`) : masquage des contrôles UI (boutons, filtres, sidebar), `page-break-inside: avoid` sur les blocs résolution/hash, en-tête répété (titre séance + date) et numéro de page en footer. La sortie imprimée doit être lisible en N&B sans dépendre du contraste couleur.
- [ ] **EDITORIAL-08**: Layout `.ag-editorial` utilise **`display: grid`** (pas flex) pour structurer la colonne contenu (max-width 720px) et une colonne sidebar latérale (hash d'intégrité, méta, navigation interne) sur viewport ≥ 1024px. Sous 1024px, la grille collapse en flux vertical naturel. *Refactoring UI* : Grid pour layout, flex pour alignement intra-cellule. Évite les hacks `flex-basis` / `min-width: 0`.
- [ ] **EDITORIAL-09**: Cleanup des padding/margin **hardcodés** dans `public/assets/css/audit.css` (et tout fichier CSS touché par le wrapper `.ag-editorial`) — remplacement par les tokens existants `--space-*` / `--pad-*` / `--gap-*`. Audit baseline avant Phase 2 : compter `grep -E "(padding|margin):\s+[0-9]+" public/assets/css/audit.css` ; cible post-phase = 0. *Refactoring UI* : *"Si tu n'utilises pas le système partout, tu n'as pas un système — tu as une suggestion."*

### Layouts secondaires (Phase 3)

- [ ] **DASHBOARD-01**: Le dashboard affiche au plus 3 KPI cards (au lieu de 4 actuellement). **Le PLAN.md de la phase 3 doit nommer explicitement le KPI supprimé et justifier pourquoi il a la moindre charge décisionnelle au quotidien** (critère produit, pas process). Le KPI déposé est intégré ailleurs (lien vers `/analytics`) — aucune information perdue.
- [ ] **DASHBOARD-02**: La hero card pleine largeur affiche **3 états distincts** selon l'imminence d'une séance :
  - *Ambient* (séance prévue dans < 60 min, > 5 min) : hero card neutre, action *"Préparer la séance"*.
  - *Urgent* (séance prévue dans < 5 min) : hero card accent warning, action *"Démarrer maintenant"*.
  - *Live* (séance en cours) : hero card accent danger avec pulse douce (respect `prefers-reduced-motion`), action *"Reprendre"*.
  Aucune hero card si > 60 min — on ne crie pas pour rien.
- [ ] **DASHBOARD-03**: Les actions rapides (Créer, Importer, etc.) sont reléguées en bas du dashboard avec `--surface-sunken` pour les visuellement secondariser.
- [ ] **DASHBOARD-04**: **Empty state** quand aucune séance n'est planifiée et aucune n'a été tenue récemment (< 30 jours) : message clair en français au centre du dashboard ("Aucune séance prévue. Créez-en une pour commencer.") avec CTA primaire vers `/seances/nouvelle`. Pas d'illustration décorative — pure typographie + bouton.
- [ ] **DASHBOARD-05**: Audit + groupement des **15 shortcut-cards** actuelles : top 5 utilisés en avant (en grille principale), le reste replié derrière un disclosure "Toutes les actions" ou groupé par persona. Le PLAN.md de Phase 3 nomme les 5 retenues et justifie leur priorité (mêmes critères produit que DASHBOARD-01). *Refactoring UI* : *"Reduce by half, see what breaks. Almost nothing breaks."*
- [ ] **DASHBOARD-06**: Layout dashboard utilise **`display: grid`** pour la hero card pleine largeur + grille KPI 3 colonnes. Remplace les `flex-basis` hacks. Définit un grid template explicite (`grid-template-columns: repeat(3, 1fr)`, `grid-template-areas` si lisibilité gagnée). *Refactoring UI* : Grid pour layout, alignement parfait sans calcul.
- [ ] **LOGIN-01**: La page `/login.html` supprime l'orbe animé radial-gradient (`login.css:60`) **ET le pattern de fond `login-brand-grid`** (background-image gradient pattern). Garde le `login-brand-glow` radial atténué (single subtle gradient ≠ pattern). *Refactoring UI* : *"Patterns and textures belong on marketing pages, not on tool entry points where the user wants to do their job."*
- [ ] **LOGIN-02**: Le panel brand sur login passe de "logo + tagline + 3 features" à "logo + tagline + 1 bénéfice" — le formulaire prend plus de place.
- [ ] **LOGIN-03**: Cleanup des padding/margin **hardcodés** dans `public/assets/css/login.css` et `public/assets/css/pages.css` (top offenders Schoger : 42 hardcodes dans `pages.css`, plus dans `login.css`). Remplacement par les tokens existants `--space-*` / `--pad-*` / `--gap-*`. Audit baseline avant Phase 3 : `grep -cE "(padding|margin):\s+[0-9]+" public/assets/css/login.css public/assets/css/pages.css` ; cible post-phase = 0 sur ces deux fichiers.

### Tech debt visuelle transverse v2.3 (quick task pré-Phase 2)

- [ ] **TECH-01**: **Consolidation des box-shadow et borders** vers le design system. Baseline mesurée : 73 box-shadow distinctes + 57 border distinctes dans `public/assets/css/`. Cible : ≤ 6 shadow tokens (`--shadow-xs/sm/md/lg/xl/2xl`) + ≤ 8 border tokens (border, border-subtle, border-strong + 5 contextuels). Audit + remplacement automatique des occurrences existantes vers le token le plus proche. Quick task (`/gsd:quick`) à exécuter **avant** `/gsd:plan-phase 2` pour que les nouveaux CSS éditoriaux héritent d'un système consolidé. *Refactoring UI* : *"Just because you can pick any value doesn't mean you should — keep elevation levels to 5-6 maximum."*

### Lexique + UX critique (Phase 4)

- [ ] **LEX-01**: Convention "membre/participant/votant" appliquée par migration cas-par-cas (lecture du contexte) sur le copy utilisateur. Distinction sémantique : membre = inscrit, participant = présent, votant = éligible au scrutin courant.
- [ ] **LEX-02**: Convention "confirmer/valider/verrouiller-archiver" appliquée. "Approuver" banni du copy de finalisation (ambiguë juridiquement).
- [ ] **MODAL-01**: Audit des modales legacy `.modal` CSS class. Migration vers `<ag-modal>` web component pour bénéficier du focus trap natif (Tab + Shift+Tab + Escape).
- [ ] **MODAL-02**: Toutes les modales actives doivent permettre Escape pour fermer (a11y critique). Ajout d'un test E2E qui ouvre une modale et vérifie que Escape la ferme + restore le focus à l'élément précédent.
- [ ] **MODAL-03**: **Affordance des triggers** : tous les boutons/liens qui ouvrent une `<ag-modal>` doivent porter `aria-haspopup="dialog"` + un signifiant visuel (ellipsis "…", icône, ou suffixe textuel). Norman : un focus trap interne est inutile si l'utilisateur ne sait pas qu'il s'apprête à entrer dans un dialog. Audit + correctifs sur les triggers de Phase 4 dans le même PR que MODAL-01/02.
- [ ] **ERR-01**: Top 50 codes `ErrorDictionary.php` (les plus utilisés) enrichis avec un "next-step" actionnable. Exemple : `"Vous avez déjà voté sur cette résolution."` → `"Vous avez déjà voté sur cette résolution. Pour modifier, demandez à l'opérateur d'annuler le précédent."`
- [ ] **ERR-02**: Test PHPUnit `tests/Security/UxConventionsTest.php` qui scanne ErrorDictionary et exige au moins une virgule + un verbe d'action (impératif ou subjonctif) dans chaque message des 50 codes les plus utilisés. Filet permanent contre la régression. **Note pour le planner :** `tests/Security/CopyConventionsTest.php` existe déjà depuis v2.2 phase 4 (3 cas, 708 assertions — terms forbidden, no "secrétaire de séance", no leftover placeholders). `UxConventionsTest` est complémentaire (cible `ErrorDictionary` spécifiquement) — ne pas dupliquer ni étendre `CopyConventionsTest`.
- [ ] **ERR-03**: Le test `UxConventionsTest` rejette aussi une **liste de phrases creuses** (regex), même si la forme passe (virgule + verbe) : `/réessayer\.?$/i`, `/contactez (le|l')admin/i`, `/erreur survenue/i`, `/une erreur est survenue/i`, `/veuillez réessayer plus tard/i`. Un message de la liste top 50 qui matche est un échec de test — il doit être réécrit avec un next-step concret.
- [ ] **ERR-04**: **Audit prévention** sur les 5 codes `ErrorDictionary` les plus émis sur les 30 derniers jours (ou stat équivalente issue des logs) : pour chacun, le PLAN.md de Phase 4 doit répondre *"peut-on faire disparaître cette erreur par contrainte UI plutôt que de la rattraper ?"*. Exemple : `already_voted` → désactiver le bouton vote après soumission (Norman : *constraints > error messages*). Au moins 2 des 5 codes doivent être marqués "prévenu en v2.3" ou "prévention reportée v2.4 avec rationale".

---

## v2 Requirements (deferred)

### UX backlog v2 (post-v2.3)

- **UX-V2-01**: Animation système (transitions cross-page, skeleton loading enrichi)
- **UX-V2-02**: A/B testing visuel (différentes hiérarchies KPI sur dashboard)
- **UX-V2-03**: Mobile-first redesign de la vue opérateur (responsive ≠ mobile-native)
- **UX-V2-04**: Empty states enrichis sur toutes les pages (illustrations légères)
- **UX-V2-05** *(Schoger S-1)*: **Cockpit declutter** — réduire la densité visuelle de `operator.htmx.html` de 70 boutons visibles en mode exec à ≤ 25. Pour chaque bouton secondary/ghost actuel, décider : visible permanent OU menu contextuel (`...`) OU drawer OU hover-revealed. *Refactoring UI Ch.1* : *"You can't make everything stand out — you must demote the rest."* Phase dédiée v2.4 (effort M-L). Préserver les flows existants (lancer/fermer/passer motion). Hors scope strict v2.3 — la Phase 1 cockpit a ajouté la hiérarchie (`<ag-health-bar>`, raccourcis), reste à enlever le bruit.
- **UX-V2-06** *(Schoger S-6)*: **Persona color confinement** — sur `operator.htmx.html` (et autres vues multi-rôle), les 6 couleurs persona (admin/président/opérateur/auditeur/votant/public) doivent être confinées à 1-2 surfaces d'identité (badge sidebar, bordure left du nom utilisateur). Pas de coloration persona sur les composants fonctionnels (boutons, panneaux, états) qui restent dans la palette sémantique unifiée. Aujourd'hui : 11 couleurs distinctes coexistent sur la même page (6 personas + 5 sémantiques) — l'œil ne sait plus ce qui est urgent. *Refactoring UI Ch.5* : *"Use color to draw attention, not to label everything."* Effort M, post-v2.3.

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
| COCKPIT-01 | Phase 1 | Done (01.1 + 01.3) |
| COCKPIT-02 | Phase 1 | Done (01.3) |
| COCKPIT-03 | Phase 1 | Done (01.1 + 01.3) |
| COCKPIT-04 | Phase 1 | Done (01.1) |
| COCKPIT-05 | Phase 1 | Done (01.1 + 01.3) |
| COCKPIT-06 | Phase 1 | Done (01.2 + 01.3) |
| COCKPIT-07 | Phase 1 | Done (01.3) |
| EDITORIAL-01 | Phase 2 | Pending |
| EDITORIAL-02 | Phase 2 | Pending |
| EDITORIAL-03 | Phase 2 | Pending |
| EDITORIAL-04 | Phase 2 | Pending |
| EDITORIAL-05 | Phase 2 | Pending |
| EDITORIAL-06 | Phase 2 | Pending |
| EDITORIAL-07 | Phase 2 | Pending |
| EDITORIAL-08 | Phase 2 | Pending (Schoger S-4) |
| EDITORIAL-09 | Phase 2 | Pending (Schoger S-3) |
| TECH-01 | Quick task pré-Phase 2 | Pending (Schoger S-2) |
| DASHBOARD-01 | Phase 3 | Pending |
| DASHBOARD-02 | Phase 3 | Pending |
| DASHBOARD-03 | Phase 3 | Pending |
| DASHBOARD-04 | Phase 3 | Pending |
| DASHBOARD-05 | Phase 3 | Pending (Schoger S-8) |
| DASHBOARD-06 | Phase 3 | Pending (Schoger S-4) |
| LOGIN-01 | Phase 3 | Pending (amendée Schoger S-5) |
| LOGIN-02 | Phase 3 | Pending |
| LOGIN-03 | Phase 3 | Pending (Schoger S-3) |
| LEX-01 | Phase 4 | Pending |
| LEX-02 | Phase 4 | Pending |
| MODAL-01 | Phase 4 | Pending |
| MODAL-02 | Phase 4 | Pending |
| MODAL-03 | Phase 4 | Pending |
| ERR-01 | Phase 4 | Pending |
| ERR-02 | Phase 4 | Pending |
| ERR-03 | Phase 4 | Pending |
| ERR-04 | Phase 4 | Pending |

**Coverage:**
- v1 requirements: 36 total (22 bootstrap + 7 revue Zhuo/Norman 2026-04-29 + 7 revue Schoger 2026-04-30 dont 1 quick task transverse)
- Mapped to phases: 36
- Unmapped: 0 ✓
- v2 deferred backlog: 6 entrées (UX-V2-01..04 + UX-V2-05 cockpit declutter Schoger S-1 + UX-V2-06 persona color confinement Schoger S-6)

---

*Requirements defined: 2026-04-29 — informed by .planning/research/SUMMARY.md*
*Last updated: 2026-04-30 — revue UX Schoger (.planning/v2.3-UX-REVIEW-SCHOGER.md) ajoute EDITORIAL-08/09, DASHBOARD-05/06, LOGIN-03, TECH-01 (quick task pré-Phase 2), backlog UX-V2-05/06 ; amende EDITORIAL-01 (text-align lock), EDITORIAL-02 (audit filter tabs), LOGIN-01 (login-brand-grid removal)*
