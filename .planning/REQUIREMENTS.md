# Requirements: AgVote v2.3 Layout Refonte & UX Polish

**Defined:** 2026-04-29
**Core Value:** Compléter la refonte visuelle initiée en v2.2 sur les écrans à plus haute charge émotionnelle, appliquer la convention lexicale unifiée, et résoudre le backlog UX/UI critique. Test ultime : un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.

**Source des requirements :** items reportés depuis v2.2 (L01, L03, L04, L05, X01) + audit UX/UI initial (3 critiques modales, 8 a11y, 16 responsive/contrastes, 7 polish) + critique Norman/Zhuo (quorum-as-a-feeling, error → next-step) + recherche inline `.planning/research/SUMMARY.md` + revue UX 2026-04-29 (`.planning/v2.3-UX-REVIEW.md` — hiérarchie cockpit, prévention quorum, raccourcis clavier, print, empty state, prévention erreurs, modal triggers).

---

## v1 Requirements

### Cockpit Opérateur live (Phase 1)

- [ ] **COCKPIT-01**: Une barre santé séance unique s'affiche au top de la vue exécution opérateur, persistante pendant toute la durée de la séance, avec **deux niveaux hiérarchiques** :
  - *Primary* (typo dominante, action-relevant) : Quorum + Résolution active
  - *Ambient* (pill 12-13px, télémétrie système) : SSE state + Votants connectés
  L'œil de l'opérateur sous stress doit cibler le primary sans concurrence visuelle de l'ambient.
- [ ] **COCKPIT-02**: L'indicateur Quorum (primary) affiche en permanence l'état atteint (vert) ou non-atteint (rouge), avec le ratio votants présents / quorum requis. **L'indicateur "personnes" principal mesure les votes restants sur la résolution active** (`Votes restants : 23 / 142`), répondant à la question opérationnelle de l'opérateur — pas une métrique de connectivité technique. Plus de notification toast éphémère.
- [ ] **COCKPIT-03**: Quand le quorum bascule en non-atteint pendant une séance, une bordure danger animée (pulse 1.5s, opacity max 0.6) apparaît autour de la zone vote — respecte `prefers-reduced-motion: reduce`.
- [ ] **COCKPIT-04**: La barre santé devient un stack vertical en responsive (< 768px) plutôt qu'une compression horizontale illisible.
- [ ] **COCKPIT-05**: Un nouveau custom element `<ag-health-bar>` encapsule la logique : data-attributes pour les valeurs, animations CSS, responsive collapse, tests d'isolation.
- [ ] **COCKPIT-06**: Raccourcis clavier sur la vue exécution opérateur (sous stress, le clavier est plus rapide que la souris) : `L` lance le vote actif, `F` ferme le scrutin actif, `→` ou `N` passe à la résolution suivante, `?` affiche un overlay de la liste des raccourcis. Ne s'activent pas dans les inputs/textareas/contenteditable. Indication visuelle discrète sur les boutons concernés (tooltip avec la touche).
- [ ] **COCKPIT-07**: État intermédiaire **"quorum à risque"** affiché quand le ratio descend sous 110 % du seuil mais reste atteint. Couleur warning douce (jamais rouge — le quorum est encore atteint), pas de pulse. Bascule visuelle anticipée → l'opérateur a 30s pour mobiliser au lieu de 0. Prévention > détection > récupération (Norman).

### Pages éditoriales (Phase 2)

- [ ] **EDITORIAL-01**: Les pages `/audit`, `/trust`, `/archives`, `/report` adoptent un wrapper `.ag-editorial` avec `max-width: 720px`, `font-family: var(--font-display)` (Fraunces), line-height 1.55-1.6 sur le contenu.
- [ ] **EDITORIAL-02**: Les contrôles UI (boutons, filtres, dropdowns) restent en `var(--font-sans)` (Bricolage) — le serif est réservé au contenu lu.
- [ ] **EDITORIAL-03**: Les hashes/UUID/codes affichés (audit chain, vote tokens, IDs) utilisent `var(--font-mono)` (JetBrains Mono).
- [ ] **EDITORIAL-04**: Les numéros de résolution dans le PV apparaissent en pill `--radius-pill` monospace **uniquement en en-tête de section, en liste, ou en tableau** (jamais inline dans un paragraphe serif — le pill casse alors le rythme de lecture). Inline en flux, le numéro reste en mono sans pill.
- [ ] **EDITORIAL-05**: Le hash d'intégrité du PV est affiché en bas du document avec un lien "Vérifier l'intégrité" actionnable. Le modal d'ouverture **doit commencer par un préambule pédagogique en français** avant la chaîne `audit_events` (sinon on montre du jargon — l'inverse de "rassurant"). Texte de référence : *"Voici la preuve que ce PV n'a pas été modifié depuis le [date]. Chaque ligne ci-dessous est un sceau cryptographique reliant la précédente — modifier une seule virgule briserait la chaîne."*
- [ ] **EDITORIAL-06**: Sous 768px, la largeur de lecture passe à 100% avec padding latéral (pas de scroll horizontal sur petit écran).
- [ ] **EDITORIAL-07**: Styles `@media print` sur les pages éditoriales (`/audit`, `/trust`, `/archives`, `/report`) : masquage des contrôles UI (boutons, filtres, sidebar), `page-break-inside: avoid` sur les blocs résolution/hash, en-tête répété (titre séance + date) et numéro de page en footer. La sortie imprimée doit être lisible en N&B sans dépendre du contraste couleur.

### Layouts secondaires (Phase 3)

- [ ] **DASHBOARD-01**: Le dashboard affiche au plus 3 KPI cards (au lieu de 4 actuellement). **Le PLAN.md de la phase 3 doit nommer explicitement le KPI supprimé et justifier pourquoi il a la moindre charge décisionnelle au quotidien** (critère produit, pas process). Le KPI déposé est intégré ailleurs (lien vers `/analytics`) — aucune information perdue.
- [ ] **DASHBOARD-02**: La hero card pleine largeur affiche **3 états distincts** selon l'imminence d'une séance :
  - *Ambient* (séance prévue dans < 60 min, > 5 min) : hero card neutre, action *"Préparer la séance"*.
  - *Urgent* (séance prévue dans < 5 min) : hero card accent warning, action *"Démarrer maintenant"*.
  - *Live* (séance en cours) : hero card accent danger avec pulse douce (respect `prefers-reduced-motion`), action *"Reprendre"*.
  Aucune hero card si > 60 min — on ne crie pas pour rien.
- [ ] **DASHBOARD-03**: Les actions rapides (Créer, Importer, etc.) sont reléguées en bas du dashboard avec `--surface-sunken` pour les visuellement secondariser.
- [ ] **DASHBOARD-04**: **Empty state** quand aucune séance n'est planifiée et aucune n'a été tenue récemment (< 30 jours) : message clair en français au centre du dashboard ("Aucune séance prévue. Créez-en une pour commencer.") avec CTA primaire vers `/seances/nouvelle`. Pas d'illustration décorative — pure typographie + bouton.
- [ ] **LOGIN-01**: La page `/login.html` supprime l'orbe animé radial-gradient (`login.css:60`).
- [ ] **LOGIN-02**: Le panel brand sur login passe de "logo + tagline + 3 features" à "logo + tagline + 1 bénéfice" — le formulaire prend plus de place.

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
| COCKPIT-06 | Phase 1 | Pending |
| COCKPIT-07 | Phase 1 | Pending |
| EDITORIAL-01 | Phase 2 | Pending |
| EDITORIAL-02 | Phase 2 | Pending |
| EDITORIAL-03 | Phase 2 | Pending |
| EDITORIAL-04 | Phase 2 | Pending |
| EDITORIAL-05 | Phase 2 | Pending |
| EDITORIAL-06 | Phase 2 | Pending |
| EDITORIAL-07 | Phase 2 | Pending |
| DASHBOARD-01 | Phase 3 | Pending |
| DASHBOARD-02 | Phase 3 | Pending |
| DASHBOARD-03 | Phase 3 | Pending |
| DASHBOARD-04 | Phase 3 | Pending |
| LOGIN-01 | Phase 3 | Pending |
| LOGIN-02 | Phase 3 | Pending |
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
- v1 requirements: 29 total (22 bootstrap + 7 ajoutés par revue UX 2026-04-29)
- Mapped to phases: 29
- Unmapped: 0 ✓

---

*Requirements defined: 2026-04-29 — informed by .planning/research/SUMMARY.md*
*Last updated: 2026-05-04 — milestone v2.5 ajoute 10 reqs (HEARTBEAT-V25-01..04, LOG-V25-01..04, COCKPIT-V25-01, TOKENS-V25-01) sourcés de v2.4-MILESTONE-AUDIT.md tech_debt frontmatter*

---

## Milestone v2.5 — Real-time Live Cockpit + Logger Migration

**Defined:** 2026-05-04 (post v2.4-MILESTONE-AUDIT.md `tech_debt` verdict)
**Goal:** Boucler les 12 items deferred du milestone v2.4 — observabilité serveur réelle, signal SSE temps réel autonome, finitions cockpit.

### v2.5 Phase 5 (continued numbering): SSE Live Pulse

- [x] **HEARTBEAT-V25-01**: `public/api/v1/events.php` émet `meeting.heartbeat` toutes les 10s avec payload `{meeting_id, server_time, status, validated_at, quorum: {applied, met, present_members, eligible_members, present_weight, eligible_weight}, operator_count}`. Chaque sub-query (status, quorum, operator_count) try/catch isolée — un échec ne casse pas la boucle SSE. *Livré commit `02179ea`.*
- [x] **HEARTBEAT-V25-02**: Frontend `operator-realtime.js` consomme `meeting.heartbeat` via `applyHeartbeat(data)` qui rafraîchit `quorumStatusBadge`, `quorumStatusDetail`, badge présence opérateurs, timestamp SSE. Hash de payload (`JSON.stringify({s, q, o})`) pour skip DOM thrash sur snapshots identiques. Détection transition quorum (false → true) → toast "Quorum atteint !". `event-stream.js` whitelist mise à jour. *Livré commit `02179ea`.*
- [ ] **HEARTBEAT-V25-03**: Test PHPUnit `tests/Unit/Sse/HeartbeatPayloadTest.php` qui vérifie `buildHeartbeatPayload()` retourne les clés attendues sur cas nominal (séance live + quorum applied) et dégrade proprement (séance introuvable, tenant null, redis sCard fail). Mockable via repos nullable. ≥3 tests / ≥10 assertions GREEN.
- [ ] **HEARTBEAT-V25-04**: Spec Playwright `tests/e2e/specs/sse-heartbeat.spec.js` qui ouvre la cockpit operator sur séance live, attend ≥12s de connexion SSE, asserte ≥1 event `meeting.heartbeat` reçu et `quorumStatusBadge` reflète le payload. Skip-tag `@integration` si `seedMeeting` indisponible.

### v2.5 Phase 6: Logger Migration & Error Tracking

- [ ] **LOG-V25-01**: 47 sites `error_log()` legacy identifiés par audit v2.4 P2.3 migrés vers `Logger::errorContext($message, $code, $context)`. Liste cible figée dans `.planning/phases/06-logger-migration/AUDIT.md` produite avant migration. Atomic commit per fichier. Aucun `error_log(` brut ne doit rester en code applicatif (hors `app/Core/Logger.php`).
- [ ] **LOG-V25-02**: Table `error_events` créée via migration SQL (`database/migrations/`) avec colonnes `id, occurred_at, request_id, tenant_id, user_id, error_code, http_status, route, payload (jsonb)`. `api_fail($code, $status)` enrichi pour capturer (insert async ou direct) chaque retour erreur serveur. Tests PHPUnit : `ErrorEventsRepositoryTest` (insert + retrieval + tenant isolation) + `ApiFailCaptureTest` (3 codes émis → 3 rows persistées).
- [ ] **LOG-V25-03**: `/admin/error-stats` (livré v2.4 P2.3 avec source `audit_events` filtrée) re-câblée sur `error_events` avec retrait du banner "limitation 6 actions audit-flavored". Page affiche : top 10 codes émis sur 7j, courbe émission par heure, drill-down par tenant. RBAC admin préservé. `AdminErrorStatsControllerTest` couvre 4 cas (admin → 200 + data, operator → 403, codes filtrés par tenant, pagination ≥100).
- [ ] **LOG-V25-04**: Instrumentation HTMX du tracking next-step ErrorDictionary — chaque suggestion cliquée émet beacon `POST /api/v1/metrics/next-step-clicked` avec `{error_code, suggestion_text, ts}`. Compteur agrégé par code consultable dans `/admin/error-stats` (% clics sur suggestion vs ignorance). Métrique valide la valeur ajoutée des next-steps v2.3 P4.4.

### v2.5 Phase 7: Cockpit Polish résiduel

- [ ] **COCKPIT-V25-01**: Sub-tab Avancé du cockpit operator ne fait plus passer le compte de cliquables visibles à >25 quand activé. Caveat documenté v2.4 audit ("peut faire passer à ~28"). Fix typique 1-line CSS. Spec Playwright `cockpit-button-count.spec.js` étendu d'1 cas (sub-tab activé → ≤25).
- [ ] **TOKENS-V25-01**: Audit des 49 tokens 1-site introduits v2.4 P4.3 dans `design-system.css` (D-08 amendé). Pour chaque : décision dans `.planning/v2.5-TOKENS-AUDIT.md` : *keep* (valeur unique légitime) ou *consolidate* (rapprocher d'un token existant + migrer le call-site). Objectif : ramener tokens 1-site sous 30. Atomic commit per consolidation.

---

### v2.5 Traceability

| Requirement | Phase | Status | Evidence |
|-------------|-------|--------|----------|
| HEARTBEAT-V25-01 | 5 | ✓ Done | commit `02179ea` |
| HEARTBEAT-V25-02 | 5 | ✓ Done | commit `02179ea` |
| HEARTBEAT-V25-03 | 5 | Pending | — |
| HEARTBEAT-V25-04 | 5 | Pending | — |
| LOG-V25-01 | 6 | Pending | — |
| LOG-V25-02 | 6 | Pending | — |
| LOG-V25-03 | 6 | Pending | — |
| LOG-V25-04 | 6 | Pending | — |
| COCKPIT-V25-01 | 7 | Pending | — |
| TOKENS-V25-01 | 7 | Pending | — |

**v2.5 Coverage:**
- Total requirements: 10
- Mapped to phases: 10 ✓
- Already satisfied: 2/10 (heartbeat backend + frontend, livrés ahead of GSD planning)
- Pending: 8/10
