# AG-Vote — Plan de refonte complet (Frontend + Backend)

> **Méthode Linus** : petits incréments vérifiables, fondations d'abord, chaque phase livre un résultat testable, zéro régression.
>
> **Source de vérité** : wireframe `docs/wireframe/ag_vote_v3_19_2.html` (v3.19.2 « Acte Officiel »)
>
> **Documents de référence** :
> - `docs/directive-projet.md` — Spécifications fonctionnelles dérivées du wireframe
> - `docs/PHASES_FRONTEND.md` — Plan frontend détaillé (10 phases, tâches unitaires)
> - `docs/dev/ARCHITECTURE.md` — Architecture technique actuelle
> - `docs/dev/cahier_des_charges.md` — Cahier des charges validé v1.1

---

## 0. Analyse des écarts — Wireframe vs État actuel

### 0.1 Écarts structurels (architecture)

| Aspect | État actuel | Cible wireframe | Impact |
|--------|------------|-----------------|--------|
| **Rôles système** | 4 rôles (`admin`, `operator`, `auditor`, `viewer`) | 7 rôles (`admin`, `gestionnaire`, `opérateur`, `président`, `secrétaire`, `scrutateur`, `votant`) | Backend : refonte RBAC |
| **Machine à états séances** | 7 états (`draft → scheduled → frozen → live → paused → closed → validated → archived`) | 6 états wireframe (`brouillon → convocations → en_cours → clôturée → pv_envoyé → archivée`) | Backend : mapping/adaptation |
| **Sidebar** | Fixe 210px dans le flux CSS Grid | Rail 58px → expanded 252px, `position: fixed`, overlay au hover | Frontend pur |
| **Header** | Sticky avec `backdrop-filter: blur(12px)` | Glassmorphisme `blur(20px)`, contexte page, recherche Ctrl+K | Frontend pur |
| **Mobile** | Sidebar drawer hamburger | Sidebar drawer + bottom nav 5 boutons | Frontend pur |
| **Design system** | Inter/Indigo/Slate, 30+ tokens | Bricolage Grotesque/Fraunces/Encre/Parchemin, 52+ tokens | Frontend pur |
| **Pages** | 14 pages `.htmx.html` + `login.html` + `index.html` | 16 pages dans le wireframe | Mapping 1:1 possible (voir §0.3) |
| **Composants Web** | 8 composants `ag-*` | 22 composants dans le wireframe | +14 nouveaux composants |
| **Wizard** | Création séance dans `operator-tabs.js` (inline) | Wizard dédié 4-5 étapes avec stepper | Frontend + routing backend |
| **Hub séance** | Page opérateur multi-onglets | Hub 6 étapes guidées (stepper vertical) | Frontend + workflow backend |
| **Convocations** | Système email existant (invitations) | Envoi convocations, rappels, 2e convocation, suivi statut | Backend : à enrichir |
| **Émargement** | Système attendance existant | Feuille d'émargement avec signature, export PDF | Backend : à enrichir |
| **PostSession** | Page `postsession.htmx.html` existante | 4 étapes (Vérification → Validation → PV → Envoi) | Frontend refonte |
| **Signature eIDAS** | Non implémenté | Signature avancée/qualifiée dans PostSession | Backend : intégration externe |
| **Archivage ZIP** | Non implémenté | Archive ZIP horodatée + SHA-256 | Backend : nouveau endpoint |
| **Visite guidée** | Non implémenté | 7 parcours, 23 étapes, composant `ag-guided-tour` | Frontend pur |
| **Recherche globale** | Non implémenté | Overlay Ctrl+K avec navigation clavier | Frontend pur |
| **Session timeout** | Non implémenté | Bannière d'alerte, prolongation, déconnexion auto | Frontend + backend |
| **Fuseaux horaires** | Non implémenté | 60 fuseaux dont DOM-TOM, composant `TZPicker` | Frontend + colonne BDD |

### 0.2 Écarts de couleurs et visuels

| Élément | Actuel | Wireframe | Notes |
|---------|--------|-----------|-------|
| Fond principal | `#f8fafc` (Slate) | `#EDECE6` (Parchemin) | Changement de palette complet |
| Accent primaire | `#4f46e5` (Indigo) | `#1650E0` (Bleu encre) | Toutes les interactions |
| Sidebar fond | `var(--color-surface)` (blanc/gris) | `#0C1018` (quasi-noir) | Contraste radical |
| Police UI | Inter | Bricolage Grotesque | Google Fonts |
| Police titres | Inter (bold) | Fraunces (serif display) | Identité « Acte Officiel » |
| Bouton primaire | `background: var(--color-primary)` plat | Gradient `180deg` avec `box-shadow` depth | Plus de relief |
| Cards | Bordure + ombre légère | Bordure + ombre + hover lift `translateY(-2px)` | Interactions enrichies |
| Tags statut | `.badge-*` (arrondis pleins) | `.tag-*` + `.tag-accent/danger/success/warn/purple` avec icônes | Diversification couleurs |
| Focus ring | `box-shadow: 0 0 0 3px rgba(79,70,229,.35)` | Double anneau `0 0 0 2px #fff, 0 0 0 4px rgba(22,80,224,.4)` | Accessibilité améliorée |
| Mode sombre | Partiel (certains tokens) | Complet (52 tokens redéfinis) | Refonte dark complète |

### 0.3 Mapping pages wireframe → fichiers existants

| Page wireframe | Route | Fichier actuel | Action |
|---------------|-------|----------------|--------|
| Landing | `/` | `public/index.html` | Refondre |
| Login | `/login` | `public/login.html` | Refondre |
| Dashboard | `/dashboard` | `public/admin.htmx.html` | Restyler + restructurer |
| Séances | `/seances` | `public/meetings.htmx.html` | Restyler + vue calendrier |
| Wizard | `/seances/new` | _(inline dans operator-tabs.js)_ | **Créer** page dédiée |
| Hub | `/seances/:id` | `public/operator.htmx.html` (partie) | Refondre en hub guidé |
| Operator | `/seances/:id/live` | `public/operator.htmx.html` | Restyler profondément |
| PostSession | `/seances/:id/cloture` | `public/postsession.htmx.html` | Refondre en 4 étapes |
| Votant | `/vote/:token` | `public/vote.htmx.html` | Restyler |
| Ecran | `/seances/:id/ecran` | `public/public.htmx.html` | Restyler |
| Audit | `/audit` | `public/trust.htmx.html` | Refondre + timeline |
| Archives | `/archives` | `public/archives.htmx.html` | Restyler |
| Membres | `/membres` | `public/members.htmx.html` | Restyler |
| Utilisateurs | `/utilisateurs` | `public/admin.htmx.html` (section) | Extraire ou garder onglet |
| Paramètres | `/parametres` | `public/admin.htmx.html` (section) | Enrichir (6 onglets) |
| Aide | `/aide` | `public/help.htmx.html` | Restyler + visite guidée |
| _Stats_ | `/stats` | `public/analytics.htmx.html` | Restyler |

### 0.4 Écarts de comportements interactifs

| Comportement | État actuel | Wireframe | Phase |
|-------------|------------|-----------|-------|
| **Sidebar pin** | Non | Toggle pin + localStorage | Phase 2 |
| **Sidebar scroll fade** | Non | Gradients haut/bas quand scroll | Phase 2 |
| **Sidebar groupes collapsibles** | Non | Chevron animé, divider en rail | Phase 2 |
| **Bottom nav mobile** | Non | 5 boutons fixes en bas | Phase 2 |
| **Recherche globale Ctrl+K** | Non | Overlay avec résultats navigables au clavier | Phase 3 |
| **Notifications dropdown** | Non | Panel depuis la cloche avec items + « Tout lire » | Phase 3 |
| **Toast empilage** | Toast unique | Stack vertical, auto-dismiss 4.2s, animation in/out | Phase 3 |
| **Modal focus-trap** | `confirm()` natif ou modal simple | Focus cycle Tab/Shift+Tab, Escape, backdrop blur | Phase 3 |
| **Accordion animation** | Non | Animation hauteur, chevron rotation 180° | Phase 3 |
| **Chips filtres** | Non (select ou liens) | Toggle actif avec couleur accent, multi-select | Phase 3 |
| **KPI hover** | Non | `translateY(-2px)`, ombre accrue, bordure accent | Phase 1/3 |
| **Card lift** | Non | Hover `translateY(-2px)` + shadow-md + bordure accent mix | Phase 1/3 |
| **Stepper horizontal** | Non | Dots numérotés avec états done/active/pending, lignes connectrices | Phase 3 |
| **Stepper vertical (Hub)** | Non | Dots + lignes verticales + labels | Phase 6 |
| **Empty states** | Partiels | Illustration + message + CTA | Phase 4+ |
| **Skeleton loading** | Existant | Shimmer animation, conformité wireframe | Phase 1 |
| **Breadcrumb** | Non | Fil d'Ariane avec séparateurs | Phase 3 |
| **Scroll-to-top** | Non | Bouton flottant visible après 300px scroll | Phase 3 |
| **Session timeout banner** | Non | Bannière fixe en bas, timer, bouton prolonger | Phase 10 |
| **Visite guidée spotlight** | Non | `clip-path` spotlight + bulle positionnée + 7 parcours | Phase 10 |
| **Onboarding banner** | Non | Gradient accent, dismissable, localStorage | Phase 4 |
| **Print styles** | Partiels | @media print complet (masquer shell, fond blanc) | Phase 10 |
| **Vue calendrier séances** | Non | Grille CSS 7 colonnes, événements colorés | Phase 5 |
| **Drag-drop import CSV** | Upload simple | Zone drop stylée | Phase 6 |
| **Timer dégressif votant** | Non | Countdown MM:SS, alerte rouge < 30s | Phase 7 |
| **Auto-avancement résolutions** | Non | Transition animée après proclamation | Phase 7 |
| **Raccourcis clavier opérateur** | Non | P = Proclamer, F = Fermer le vote | Phase 7 |
| **Passerelle art. 25-1** | Non | Proposition automatique si ≥ 1/3 des voix | Phase 7 (+ backend) |
| **Chronomètre séance** | Non | Compteur temps réel MM:SS | Phase 7 |
| **Barre guidance opérateur** | Non | Message contextuel selon étape du vote | Phase 7 |
| **Quorum non atteint modale** | Non | 3 options : Reporter, Suspendre 30min, Continuer | Phase 7 (+ backend) |
| **Confirmation vote 2 temps** | Confirmation simple | Sélection → Confirmer → Merci (check animé) | Phase 7 |

---

## 1. Phases de développement

### Vue d'ensemble

```
FONDATIONS (phases 1-3) → séquentielles, chacune dépend de la précédente
  Phase 1 : Design Tokens « Acte Officiel »
  Phase 2 : Shell (sidebar rail, header glass, mobile)
  Phase 3 : Composants partagés (Web Components + CSS)

PAGES (phases 4-9) → parallélisables après phase 3
  Phase 4 : Pages statiques (Landing, Dashboard, Aide)
  Phase 5 : Pages CRUD (Séances, Membres, Utilisateurs, Archives)
  Phase 6 : Wizard et Hub (séance)
  Phase 7 : Pages en direct (Opérateur, Votant, Écran) ← le plus complexe
  Phase 8 : PostSession et Statistiques
  Phase 9 : Audit et Paramètres

INTÉGRATION (phase 10)
  Phase 10 : Visite guidée, intégration bout-en-bout, a11y, print
```

### Matrice de dépendances

```
Phase 1 (Design Tokens)
  └── Phase 2 (Shell)
        └── Phase 3 (Composants partagés)
              ├── Phase 4 (Landing, Dashboard, Aide)
              ├── Phase 5 (Séances, Membres, Utilisateurs, Archives)
              ├── Phase 6 (Wizard, Hub)
              ├── Phase 7 (Opérateur, Votant, Écran)    ← le + complexe
              ├── Phase 8 (PostSession, Stats)
              └── Phase 9 (Audit, Paramètres)
        └── Phase 10 (Visite guidée, intégration, a11y)
```

---

## 2. Détail des changements backend par phase

> Le plan frontend détaillé est dans `docs/PHASES_FRONTEND.md`.
> Ce document complète avec les impacts backend identifiés.

### Phase 1 — Design Tokens (Backend : aucun)

Aucun changement backend. Uniquement `design-system.css` et les `<link>` Google Fonts.

---

### Phase 2 — Shell (Backend : minimal)

**Changements backend** :

| Changement | Fichier(s) | Description |
|------------|-----------|-------------|
| Route sidebar partial | `public/partials/sidebar.html` | Restructurer le HTML pour le nouveau markup rail/expanded |
| Header contexte page | `app/routes.php` ou templates | Le header doit afficher le nom de la page courante — possible via `data-*` attributs dans le HTML |

**Aucun nouveau endpoint API nécessaire.**

---

### Phase 3 — Composants partagés (Backend : notifications API)

**Changements backend** :

| Changement | Fichier(s) | Description |
|------------|-----------|-------------|
| API notifications | `public/api/v1/notifications.php` | Endpoint GET pour le panel notifications dropdown — **existe déjà** via `NotificationRepository` |
| Marquer lu | `public/api/v1/notifications_read.php` | Endpoint PUT marquer toutes comme lues — **vérifier si existe** |

**Le backend notification est déjà en place** (`NotificationsService`, `NotificationRepository`). Il faut vérifier que les endpoints retournent le format attendu par le panel dropdown (items avec dot coloré, message, timestamp).

---

### Phase 4 — Pages statiques (Backend : minimal)

**Changements backend** :

| Changement | Fichier(s) | Description |
|------------|-----------|-------------|
| Login formulaire | `public/api/v1/auth_login.php` | **Existe déjà** — le wireframe simule un `onLogin()`, l'implémentation réelle est en place |
| Dashboard KPI | `DashboardController.php` | **Existe déjà** — vérifier que les données retournées correspondent aux 4 KPI du wireframe |
| Aide FAQ | Statique | Les 23 questions FAQ sont en HTML statique, pas besoin d'API |

**Écart Dashboard** : le wireframe montre 4 KPI (Sessions programmées, Participants inscrits, Taux participation, Résolutions votées) + liste tâches urgentes + prochaines séances. Vérifier que `DashboardController::index()` fournit toutes ces données.

---

### Phase 5 — Pages CRUD (Backend : vue calendrier + filtres)

**Changements backend** :

| Changement | Fichier(s) | Description |
|------------|-----------|-------------|
| Vue calendrier séances | `MeetingsController.php` ou `MeetingRepository.php` | Endpoint pour récupérer les séances par mois (format calendrier) — **filtrage par plage de dates** |
| Filtres séances | `MeetingsController.php` | Le wireframe montre des filtres chips (Toutes, À venir, En cours, Terminées) — **vérifier que le filtrage par statut existe** |
| Popover actions séance | API existante | Dupliquer une séance — **vérifier si endpoint existe** |
| Membres — groupes/lots | `MemberGroupsController.php` | Les filtres par groupe/lot du wireframe — **backend existe** |
| Membres — clés de répartition | `PolicyController.php` | Drawer « Clés de répartition » — **backend existe** (vote_policies) |
| Archives — téléchargement | `ExportController.php` | Téléchargement archive complète — **vérifier format ZIP** |

**Nouveau endpoint possible** :
- `POST /api/seances/:id/duplicate` — Dupliquer une séance (si non existant)

---

### Phase 6 — Wizard et Hub (Backend : changements significatifs)

**C'est ici que le backend diverge le plus du wireframe.**

**Changements backend** :

| Changement | Fichier(s) | Impact |
|------------|-----------|--------|
| **Wizard 4 étapes** | `WizardRepository.php`, routes | Le wizard existe (`wizard_status.php`) mais il faut vérifier qu'il supporte les 4 étapes du wireframe (Infos, Participants, Résolutions, Récapitulatif) |
| **Hub 6 étapes** | `MeetingWorkflowService.php` | Le wireframe montre 6 étapes (Préparer → Convoquer → Émarger → Voter → Clôturer → Archiver). Le backend a 7 états machine. Il faut un **mapping** |
| **Convocations** | `InvitationsController.php`, `EmailController.php` | Le système d'invitations existe. Il faut mapper vers le concept « convocations » du wireframe (envoi, suivi statut, rappels, 2e convocation) |
| **Alerte délai 21 jours** | `MeetingValidator.php` | Vérifier que le délai de convocation de 21 jours (décret 17 mars 1967) est validé |
| **2e convocation** | `MeetingsController.php` | Endpoint pour générer la 2e convocation avec quorum réduit |
| **Fuseau horaire** | Table `meetings`, `MeetingsController.php` | Ajouter colonne `timezone` à la table `meetings` si absente |
| **Brouillon sessionStorage** | Frontend uniquement | Pas de changement backend |

**Mapping états machine (backend → wireframe)** :

| Backend actuel | Wireframe | Étape Hub |
|---------------|-----------|-----------|
| `draft` | `brouillon` | — (avant Hub) |
| `scheduled` | — | 1. Préparer |
| `frozen` | `convocations` | 2. Convoquer |
| `live` | `en_cours` | 3. Émarger → 4. Voter |
| `paused` | _(sous-état de en_cours)_ | 4. Voter (suspendu) |
| `closed` | `clôturée` | 5. Clôturer |
| `validated` | `pv_envoyé` | 5. Clôturer (PV envoyé) |
| `archived` | `archivée` | 6. Archiver |

**Décision** : Ne pas changer les noms d'états backend. Mapper dans le frontend via un dictionnaire de labels.

**Migration BDD potentielle** :
```sql
ALTER TABLE meetings ADD COLUMN timezone VARCHAR(50) DEFAULT 'Europe/Paris';
```

---

### Phase 7 — Pages en direct (Backend : changements critiques)

**Le cœur métier. Impacts backend significatifs.**

**Changements backend** :

| Changement | Fichier(s) | Impact |
|------------|-----------|--------|
| **Passerelle art. 25-1** | `VoteEngine.php`, `BallotsService.php` | Si un vote art. 25 échoue mais obtient ≥ 1/3 des voix → proposer 2nd vote en art. 24. Nécessite : détection du seuil 1/3, création d'une motion « passerelle », endpoint dédié |
| **Quorum non atteint — 3 options** | `MeetingWorkflowService.php`, `QuorumEngine.php` | Reporter (2e convocation), Suspendre 30 min, Continuer sous réserve. Le backend a déjà `paused` et les quorum policies. Il faut ajouter la logique de « reporter avec quorum réduit » |
| **Voix prépondérante président** | `VoteEngine.php` | En cas d'égalité Pour/Contre, le président a une voix prépondérante (art. 22). Vérifier que cette logique existe |
| **Comptage manuel** | `BallotsController.php` | Saisie manuelle des résultats (pour/contre/abstention). **Vérifier si l'endpoint existe** |
| **Demandes de parole** | `SpeechService.php`, `SpeechController.php` | File d'attente, accord/refus. **Backend existe** |
| **Timer vote** | Frontend + config | Durée de vote paramétrable — vérifier si la table `meetings` ou `motions` a un champ `vote_duration` |
| **Auto-avancement** | Frontend uniquement | Après proclamation, avancer automatiquement à la résolution suivante |

**Nouveaux endpoints potentiels** :

```
POST /api/resolutions/:id/passerelle-25-1    → Déclencher le 2nd vote art. 24
POST /api/resolutions/:id/comptage-manuel    → Saisir résultat manuel
POST /api/seances/:id/2e-convocation         → Générer la 2e convocation
```

**Vérifications backend critiques** :

1. **`VoteEngine.php`** : supporte-t-il les 4 régimes de majorité (art. 24, 25, 26, 26-1) ?
   - Le wireframe les affiche explicitement dans le wizard et l'opérateur
   - Les `vote_policies` existantes supportent des seuils configurables
   - Il faut vérifier que les labels (art. 24, etc.) sont mappés

2. **`QuorumEngine.php`** : supporte-t-il le quorum réduit pour 2e convocation ?
   - Le wireframe montre une bascule automatique du seuil quorum
   - Les `quorum_policies` existantes sont paramétrables
   - Vérifier si un champ `est_2e_convocation` existe sur `meetings`

3. **WebSocket** : `EventBroadcaster.php` existe. Vérifier que les événements correspondent :
   - `vote:open`, `vote:close`, `vote:cast`, `vote:proclaim`
   - `quorum:update`, `parole:request`, `parole:grant`
   - `session:start`, `session:suspend`, `session:close`

---

### Phase 8 — PostSession et Statistiques (Backend : signature + archivage)

**Changements backend** :

| Changement | Fichier(s) | Impact |
|------------|-----------|--------|
| **Signature eIDAS** | Nouveau service | Intégration API externe (Yousign, DocuSign, ou autre). Le wireframe montre 3 modes : aucune, avancée, qualifiée. C'est un **chantier d'intégration externe** |
| **Génération PV PDF** | `MeetingReportService.php`, `ExportService.php` | Le PV existe déjà en HTML. Vérifier la génération PDF via DomPDF |
| **Envoi PV** | `EmailController.php` | Envoi du PV signé aux participants. Vérifier le template email |
| **Archivage ZIP** | `ExportService.php` ou nouveau | Archive complète (PV + émargement + votes + audit) en ZIP horodaté avec SHA-256. **Nouveau endpoint** |
| **Statistiques** | `AnalyticsRepository.php` | Les données statistiques existent. Vérifier que toutes les métriques du wireframe sont couvertes (taux participation par séance, répartition votes, durée moyenne, séances par mois) |

**Nouveaux endpoints** :
```
POST /api/seances/:id/pv/generate    → Générer le PV (PDF)
POST /api/seances/:id/pv/sign       → Signer (eIDAS)
POST /api/seances/:id/pv/send       → Envoyer le PV
POST /api/seances/:id/archive       → Archiver (ZIP + SHA-256)
```

**Migration BDD potentielle** :
```sql
-- Table procès-verbaux si elle n'existe pas
CREATE TABLE IF NOT EXISTS meeting_reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    meeting_id UUID NOT NULL REFERENCES meetings(id),
    content TEXT,
    generated_at TIMESTAMPTZ,
    signed_at TIMESTAMPTZ,
    signature_type VARCHAR(20), -- 'none', 'advanced_eidas', 'qualified_eidas'
    signature_hash TEXT,
    sent_at TIMESTAMPTZ,
    sent_to TEXT[], -- emails
    archived_at TIMESTAMPTZ,
    archive_hash VARCHAR(64), -- SHA-256
    tenant_id UUID NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

**Note** : `MeetingReportRepository` et `MeetingReportsController` existent déjà. Vérifier les colonnes et enrichir si nécessaire.

---

### Phase 9 — Audit et Paramètres (Backend : enrichissement)

**Changements backend** :

| Changement | Fichier(s) | Impact |
|------------|-----------|--------|
| **Audit timeline** | `AuditController.php` | Le wireframe montre une vue chronologie en plus du tableau. Le backend retourne déjà les événements — il faut vérifier le format (catégorie, sévérité, horodatage) |
| **Audit filtres** | `AuditEventRepository.php` | Filtrage par catégorie (Votes, Présences, Sécurité, Système) et par sévérité. **Vérifier que les filtres sont implémentés** |
| **Paramètres 6 onglets** | `AdminController.php`, `PoliciesController.php` | Le wireframe montre 6 onglets (Règles, Clés, Sécurité, Courrier, Général, Accessibilité). Les endpoints existent partiellement |
| **Niveau CNIL** | `AdminController.php` ou config | Paramètre système pour le niveau CNIL (1, 2, 3). Ajouter si absent |
| **Séparation identité/bulletin** | `BallotsService.php` | Toggle dans les paramètres. Le wireframe montre un toggle CNIL. Vérifier que la séparation est implémentée au niveau BDD |
| **Déclaration RGAA** | Statique | Page dans les paramètres — texte statique éditable |
| **Templates courriel** | `EmailTemplatesController.php` | 5 templates éditables avec variables. **Backend existe** |

**Vérifications** :
- `AuditEventRepository` : supporte-t-il le filtrage par `category` et `severity` ?
- Les catégories d'audit du wireframe (auth, vote, quorum, procuration, émargement, système, export, signature, paramètre) correspondent-elles aux valeurs stockées ?

---

### Phase 10 — Intégration (Backend : session timeout)

**Changements backend** :

| Changement | Fichier(s) | Impact |
|------------|-----------|--------|
| **Session timeout** | `AuthMiddleware.php` | Le wireframe montre un timer d'inactivité (bannière à 2 min, déconnexion auto). Vérifier la durée de session PHP et ajouter un endpoint de prolongation |
| **Endpoint prolonger session** | `AuthController.php` | `POST /api/auth/extend-session` — prolonge la session PHP |
| **Index recherche globale** | Frontend | L'index de recherche est côté client (pages + actions statiques). Pas de changement backend |

---

## 3. Migrations BDD nécessaires

### Synthèse des modifications de schéma

| Table | Modification | Phase | Priorité |
|-------|-------------|-------|----------|
| `meetings` | Ajouter `timezone VARCHAR(50) DEFAULT 'Europe/Paris'` | Phase 6 | Haute |
| `meetings` | Ajouter `est_2e_convocation BOOLEAN DEFAULT FALSE` | Phase 7 | Haute |
| `meetings` | Ajouter `seance_parent_id UUID NULL REFERENCES meetings(id)` | Phase 7 | Haute |
| `motions` | Vérifier `vote_secret BOOLEAN` existe | Phase 6 | Moyenne |
| `motions` | Vérifier `comptage_manuel BOOLEAN` existe | Phase 7 | Moyenne |
| `motions` | Vérifier `passerelle_25_1 BOOLEAN` existe | Phase 7 | Haute |
| `meeting_reports` | Vérifier colonnes signature eIDAS | Phase 8 | Haute |
| `system_settings` | Ajouter `niveau_cnil INTEGER DEFAULT 1` si table existe | Phase 9 | Moyenne |

**Règle** : chaque migration est un fichier SQL daté dans `database/migrations/`, idempotent, avec `IF NOT EXISTS`.

---

## 4. Nouvelles routes API

### Endpoints à créer ou vérifier

| Méthode | Endpoint | Phase | Existe ? | Action |
|---------|----------|-------|----------|--------|
| POST | `/api/seances/:id/2e-convocation` | 7 | Non | Créer |
| POST | `/api/resolutions/:id/passerelle-25-1` | 7 | Non | Créer |
| POST | `/api/resolutions/:id/comptage-manuel` | 7 | À vérifier | Créer si absent |
| POST | `/api/seances/:id/pv/generate` | 8 | À vérifier | Enrichir |
| POST | `/api/seances/:id/pv/sign` | 8 | Non | Créer (intégration eIDAS) |
| POST | `/api/seances/:id/pv/send` | 8 | À vérifier | Enrichir |
| POST | `/api/seances/:id/archive` | 8 | À vérifier | Enrichir (ZIP + SHA-256) |
| POST | `/api/auth/extend-session` | 10 | Non | Créer |
| GET | `/api/seances/:id/step/:n` | 6 | Non | Créer (Hub étapes) |
| POST | `/api/seances/:id/duplicate` | 5 | À vérifier | Créer si absent |
| GET | `/api/seances/calendar` | 5 | Non | Créer (vue calendrier) |

---

## 5. Checklist de conformité wireframe — Détails manquants

### 5.1 Éléments visuels à ne pas oublier

| Élément | Page(s) | CSS wireframe | Notes |
|---------|---------|---------------|-------|
| Barre accent gauche sur titre | Toutes | `.page-title .bar` (3px, accent, radius full) | Identité visuelle forte |
| Logo mark | Header | `.logo-mark` (28px, radius 6px, fond accent) | Icône AG dans carré bleu |
| Striped rows tableaux | Toutes les tables | `tbody tr:nth-child(even) td { background: rgba(21,21,16,.022) }` | Lisibilité |
| Hover row accent | Toutes les tables | `tbody tr:hover td { background: color-mix(in srgb, var(--accent) 6%, var(--surface)) }` | Feedback |
| Overflow guard tables | Toutes les tables | `td, th { max-width: 260px; overflow: hidden; text-overflow: ellipsis }` | Protection largeur |
| Scrollbar custom | Global | `::-webkit-scrollbar { width: 5px }` | Finesse |
| Text selection | Global | `::selection { background: var(--accent-light); color: var(--accent-dark) }` | Cohérence accent |
| Smooth scroll | Global | `html { scroll-behavior: smooth }` | Navigation fluide |
| Page animation | Toutes | `.page-anim` (opacity + translateY 4px, 0.18s) | Transition entre pages |

### 5.2 Composants CSS du wireframe à reproduire exactement

| Composant CSS | Lignes wireframe | Classes | Points d'attention |
|--------------|-----------------|---------|-------------------|
| Onboarding banner | 876-894 | `.ob-banner`, `.ob-icon`, `.ob-title`, `.ob-actions` | Gradient accent, cercle décoratif, dismissable |
| Wizard progress | 857-921 | `.wizard-progress`, `.wiz-step`, `.wiz-num` | Barre accent 3px en bas de l'étape active |
| Hub stepper | 626-676 | `.hub-stepper`, `.hub-step-row`, `.hub-step-num` | Vertical, lignes connectrices colorées |
| Hub action card | 647-661 | `.hub-action`, `.hub-action-icon`, `.hub-action-title` | Bordure accent 1.5px, glow, hover lift |
| Hub identity | 674-676 | `.hub-identity`, `.hub-identity-date` | Font display, méta flex |
| Hub details toggle | 663-672 | `.hub-details-toggle`, `.hub-details-body` | Accordion avec aria-expanded |
| Operator tabs | 588-599 | `.op-tabs`, `.op-tab` | Border-bottom 2px accent quand actif |
| Operator kbd hints | 1001 | `.op-kbd` | Monospace, fond léger, bordure |
| Vote buttons | 602-617 | `.vote-btn`, `.vote-pour`, `.vote-contre`, `.vote-abst` | Grille, couleurs sémantiques, check icon, selected state |
| Tablet frame | 716-719 | `.tablet-frame`, `.tablet-body` | 780px max, radius 18px, border 1.5px |
| Votant shell | 727-731 | `.votant-shell`, `.votant-header`, `.votant-question` | Flex column, max 580px, centré |
| Device bar | 711-713 | `.device-bar`, `.device-label`, `.device-desc` | Font 700, uppercase, border-bottom |
| Standalone page | 722-724 | `.standalone-page`, `.standalone-nav`, `.standalone-content` | Pour l'écran public sans sidebar |
| CTX panel | 706-708 | `.ctx-panel` | Fond accent-light, border-left 3px accent |
| Confirm dialog | 941-944 | `.confirm-dialog`, `.confirm-icon-wrap`, `.confirm-dialog-title` | Icône 56px centrée, font display |
| Live timer | 947-948 | `.live-timer`, `.live-timer-val` | Font mono, 15px, bold |
| Search bar | 951-953 | `.search-bar-row`, `.search-bar-wrap` | Padding-left 34px pour icône |
| Header ctx | 956-957 | `.header-ctx`, `.header-ctx-name` | Badge contexte page dans header |
| Tour bubble | 844-853 | `.tour-bubble`, `.tour-header`, `.tour-icon`, `.tour-progress` | Border 2px accent, radius 14px, shadow lourde |
| Tour spotlight | 853 | `.tour-spotlight-ring` | Box-shadow 4000px overlay, border accent |

### 5.3 Animations du wireframe

| Animation | Keyframes | Durée | Usage |
|-----------|-----------|-------|-------|
| `modalIn` | scale(.96) translateY(6px) → none | 0.2s cubic-bezier(.34,1.2,.64,1) | Ouverture modale |
| `popIn` | translateY(-4px) scale(.97) → none | 0.12s cubic-bezier(.4,0,.2,1) | Popover apparition |
| `toastIn` | translateX(20px) scale(.96) → none | 0.22s cubic-bezier(.34,1.1,.64,1) | Toast apparition |
| `toastOut` | none → translateX(20px) scale(.96) | 0.18s ease | Toast disparition |
| `pageIn` | translateY(4px) opacity(0) → none | 0.18s cubic-bezier(.4,0,.2,1) | Transition de page |
| `fadeIn` | translateY(3px) opacity(0) → none | — | Éléments apparition |
| `slideUp` | translateY(10px) opacity(0) → none | — | Bannières |
| `shimmer` | background-position 200% → -200% | 1.6s infinite | Skeleton loading |
| `pulse` | box-shadow 0 → 5px → 0 | 1.8s ease-in-out infinite | Live dot |
| `spin` | rotate(0) → rotate(360deg) | — | Spinner |

### 5.4 Breakpoints du wireframe

| Breakpoint | Changements |
|-----------|-------------|
| `> 1024px` | Layout complet, grids 4 colonnes |
| `768px - 1024px` | Grids 4→2, landing body column |
| `< 768px` | Sidebar drawer, hamburger, bottom nav, main padding-bottom 76px, grids 2→1, header 46px |
| `< 480px` | Grids tous 1 col, row flex-direction column, page-title 16px, card padding réduit |

---

## 6. Priorités et risques

### Risques identifiés

| Risque | Impact | Mitigation |
|--------|--------|-----------|
| **Passerelle art. 25-1** nécessite une logique métier complexe | Haute complexité backend | Implémenter en phase 7 après validation des régimes de majorité existants |
| **Signature eIDAS** dépend d'un prestataire externe | Blocage externe | Implémenter l'interface en phase 8, stub API en attendant le prestataire |
| **Mapping états machine** backend ≠ wireframe | Confusion développeurs | Dictionnaire de labels frontend, pas de renommage backend |
| **52+ tokens CSS** à remplacer sans régression | Régression visuelle | Phase 1 dédiée, validation page par page |
| **Sidebar rail** change le layout de toutes les pages | Casse potentielle sur 14 pages | Phase 2 dédiée, validation page par page |
| **14 nouveaux Web Components** | Volume de travail | Prioriser : modal, pagination, stepper (bloquants pour phases 4+) |

### Ordre de priorité des phases

1. **Phase 1** (Design Tokens) — Fondation de tout
2. **Phase 2** (Shell) — Structure de navigation
3. **Phase 3** (Composants) — Briques réutilisables
4. **Phase 7** (Live) — Cœur métier, valeur business maximale
5. **Phase 6** (Wizard/Hub) — Expérience de création
6. **Phase 5** (CRUD) — Pages de gestion
7. **Phase 4** (Statiques) — Première impression
8. **Phase 8** (PostSession/Stats) — Clôture du cycle
9. **Phase 9** (Audit/Paramètres) — Administration
10. **Phase 10** (Intégration) — Polish final

---

## 7. Checklist pré-démarrage

Avant de commencer la phase 1, vérifier :

- [ ] Le wireframe `docs/wireframe/ag_vote_v3_19_2.html` s'ouvre correctement dans un navigateur
- [ ] Les 16 pages sont navigables dans le wireframe
- [ ] Le mode sombre fonctionne dans le wireframe
- [ ] Le responsive fonctionne dans le wireframe (< 768px, < 480px)
- [ ] Le plan frontend détaillé `docs/PHASES_FRONTEND.md` est à jour
- [ ] Ce document `docs/PLAN_REFONTE_COMPLET.md` est validé
- [ ] La directive `docs/directive-projet.md` est lue et comprise

---

## 8. Règles de commit

```
feat(phase-N): description courte

- Détail 1
- Détail 2

Réf: docs/PLAN_REFONTE_COMPLET.md
```

Chaque sous-tâche `[ ]` dans `PHASES_FRONTEND.md` = un commit atomique.
Chaque changement backend = un commit séparé avec tests.

---

## 9. Correspondance technologique wireframe → production

| Wireframe (React) | Production (PHP + HTMX + vanilla) |
|-------------------|----------------------------------|
| `useState()` | Variables JS locales, `data-*` attributs |
| `onClick={() => setPage(x)}` | `hx-get="/page"` + `hx-push-url` |
| `{data.map(item => <Row />)}` | PHP `foreach` dans le template HTMX |
| React component (`<Modal />`) | Web Component (`<ag-modal>`) |
| `useRef()` + DOM manipulation | `document.querySelector()` direct |
| Context / Zustand store | `localStorage` + `CustomEvent` + `data-*` |
| `fetch()` + `useEffect` | `hx-get` / `hx-post` + `hx-trigger` ou `api()` JS |
| CSS Modules | Shadow DOM CSS (composants) ou BEM dans `design-system.css` |
| React Router | PHP routing + `hx-push-url` + `hx-target` |
| `Portal` (modales, tooltips) | Web Component avec `position: fixed` |

---

## 10. Composants utilitaires du wireframe — Inventaire complet

> Ces composants React du wireframe doivent être transposés en Web Components vanilla ou fonctions JS.

### 10.1 Composants à créer en Web Components (`ag-*`)

| Composant wireframe | Web Component cible | Phase | Description |
|---------------------|-------------------|-------|-------------|
| `Modal` | `<ag-modal>` | 3 | Focus-trap, Escape, backdrop blur, aria-modal |
| `Stepper` | `<ag-stepper>` | 3 | Dots numérotés + lignes, états done/active/pending |
| `Pg` (Pagination) | `<ag-pagination>` | 3 | Boutons page, ellipsis, prev/next |
| `TimeInput` | `<ag-time-input>` | 3 | HH:MM split, validation, paste, flèches |
| `TZPicker` | `<ag-tz-picker>` | 3 | 59 fuseaux, recherche, dropdown |
| `Donut` | `<ag-donut>` | 3 | SVG segments proportionnels, center value |
| `CTA` (Textarea) | `<ag-textarea>` | 3 | Compteur caractères, états warn/over |
| `Av` (Avatar) | CSS `.avatar` | 3 | Initiales, 8 couleurs déterministes |
| `Popover` | `<ag-popover>` | **existant** | Restyler items/séparateurs |
| `HelpTip` | CSS `.htip` + JS portal | 3 | Tooltip au hover/focus, positionnement auto |
| `MiniBar` | CSS `.mini-bar` | 3 | Mini barres horizontales pour stats |
| `ScrollTop` | JS dans `shell.js` | 2 | Bouton flottant visible après 300px scroll |
| `GuidedTour` | `<ag-guided-tour>` | 10 | Spotlight SVG, bulle, 7 parcours, clavier |
| `GlobalSearch` | JS dans `shell.js` | 3 | Overlay Ctrl+K, index 12 pages, clavier |
| `DeviceBar` | CSS `.device-bar` | 7 | Indicateur PC/Tablette |
| `ConfirmDialog` | Extension `<ag-modal>` | 3 | Icône danger/warn, titre centré, 2 boutons |
| `Breadcrumb` | CSS `.breadcrumb` | 2 | Fil d'Ariane, 14 chemins |
| `Toast` | `<ag-toast>` | **existant** | Enrichir : stack, 4.2s auto, animation in/out |

### 10.2 Composants existants à restyler

| Composant | Changements wireframe |
|-----------|----------------------|
| `<ag-kpi>` | Hover lift, bordure hover accent, `font-display` pour la valeur |
| `<ag-badge>` | Devenir `.tag-*` avec 5 couleurs (accent, danger, success, warn, purple) + icônes |
| `<ag-spinner>` | Conformité animation `spin` wireframe |
| `<ag-toast>` | Stack vertical, 4.2s dismiss, animation `toastIn`/`toastOut`, 4 variantes couleur |
| `<ag-quorum-bar>` | Segments cliquables, seuil visuel, progression globale |
| `<ag-vote-button>` | Grille 2x2, couleurs sémantiques, check icon, état selected avec shadow |
| `<ag-searchable-select>` | Conformité styles wireframe |

### 10.3 Données mock du wireframe — Référence pour les tests

| Données | Quantité | Champs | Usage |
|---------|----------|--------|-------|
| Sessions (SEANCES_DATA) | 7 | title, date, status, color, participants, resolutions, quorum | Page séances |
| Archives (ARCH_DATA) | 7 | title, date, type, results, presence | Page archives |
| Hub steps (HUB_STEPS) | 6 | id, num, titre, desc, icon, color, dest, checks | Page hub |
| Resolutions (allRes) | 5 | id, title, majority, key, secret, status, pour, contre, abstention | Page opérateur |
| Audit events (EVENTS) | 9 | id, timestamp, event, user, hash, category, severity | Page audit |
| Navigation (NAV) | 5 groupes, 14 items | label, icon, page, badge | Sidebar |
| Breadcrumbs (CRUMBS) | 14 chemins | page_id → path[] | Header |
| Search index (SEARCH_IDX) | 12 pages | name, subtitle, icon, page | Recherche globale |
| FAQ (faqs) | 5 catégories, 23 questions | category, icon, items[{q, a}] | Page aide |
| Tours (tourSteps) | 7 parcours, 22 étapes | target, icon, title, desc | Visite guidée |
| Timezones (TZ_LIST) | 59 fuseaux | string | Composant TZPicker |
| Avatar colors | 8 couleurs | hex string | Composant Avatar |
| Utilisateurs | 5 users | nom, email, rôle, actif, dernière connexion | Page utilisateurs |
| Membres | 7 membres | nom, lot, tantièmes, tantiemes_asc, email, groupe, actif | Page membres |
| Notifications | 5 items | message, time, color, dest | Panel notifications |
| Mail templates | 5 templates | name, subject, variables | Page paramètres |
| Vote choices (CHOICES) | 4 | label, color, bg, icon | Page votant |

### 10.4 Toast messages — Inventaire complet (55 messages)

Le wireframe définit **55 messages toast** répartis sur les 16 pages. Les types utilisés :
- `success` : 32 messages (opérations réussies)
- `info` : 17 messages (informations, wireframe placeholders)
- `warn` : 6 messages (avertissements, pauses, reports)

**Configuration toast** : auto-dismiss à **4200ms**, icônes par type, `aria-live="polite"`, `role="alert"`.

---

## 11. Accessibilité wireframe — Inventaire ARIA

Le wireframe définit un niveau d'accessibilité **RGAA 97% / WCAG 2.2 AA** avec :

### 11.1 Rôles ARIA utilisés (16 rôles)

`dialog`, `menu`, `menuitem`, `navigation`, `banner`, `main`, `button`, `switch`, `listbox`, `option`, `presentation`, `alert`, `status`, `tooltip`, `progressbar`, `region`

### 11.2 Propriétés ARIA (10 propriétés)

`aria-label` (34+ instances), `aria-expanded`, `aria-haspopup`, `aria-current`, `aria-required`, `aria-modal`, `aria-labelledby`, `aria-live` (polite + assertive), `aria-atomic`, `aria-hidden`, `aria-pressed`, `aria-selected`, `aria-checked`, `aria-valuenow/min/max`

### 11.3 Features accessibilité

| Feature | Implémentation wireframe |
|---------|--------------------------|
| Skip link | `<a href="#main-content" class="skip-link">` sur landing et app |
| Focus trap modale | `useEffect` capture Tab/Shift+Tab, restore focus on close |
| Navigation clavier sidebar | Enter/Space sur groupes et items |
| Reduced motion | `@media(prefers-reduced-motion:reduce)` — supprime toutes les animations |
| Status icons (RGAA 3.1) | Chaque tag statut a une icône SVG en plus de la couleur |
| Focus ring | Double anneau `0 0 0 2px #fff, 0 0 0 4px accent-glow` |
| Scroll-to-top | Visible après 300px, `aria-label="Retour en haut"` |
| Live regions | Toast container `aria-live="polite"`, session warning `aria-live="assertive"` |

---

## 12. Résumé exécutif

### Volumétrie

| Catégorie | Quantité |
|-----------|----------|
| Pages à refondre | 16 |
| Composants Web à créer | 14 |
| Composants Web à restyler | 8 |
| CSS tokens à remplacer | 52+ |
| Animations CSS | 10 |
| Breakpoints responsive | 4 |
| Messages toast | 55 |
| Modales | 13 |
| Attributs ARIA uniques | 34+ |
| Rôles ARIA | 16 |
| Endpoints backend à créer/enrichir | 11 |
| Migrations BDD potentielles | 8 colonnes |
| FAQ questions | 23 |
| Tours guidés | 7 (22 étapes) |

### Estimation de complexité par phase

| Phase | Frontend | Backend | Total |
|-------|----------|---------|-------|
| 1 — Tokens | Haute | Aucun | Haute |
| 2 — Shell | Haute | Minimal | Haute |
| 3 — Composants | Très haute | Minimal | Très haute |
| 4 — Statiques | Faible | Minimal | Faible |
| 5 — CRUD | Moyenne | Faible | Moyenne |
| 6 — Wizard/Hub | Haute | Moyenne | Haute |
| 7 — Live | Très haute | Haute | **Très haute** |
| 8 — PostSession | Moyenne | Haute | Haute |
| 9 — Audit/Param | Haute | Moyenne | Haute |
| 10 — Intégration | Haute | Faible | Haute |

### Prochaine étape

Commencer par la **Phase 1 — Design Tokens** : remplacer les 52+ variables CSS dans `design-system.css` par la palette « Acte Officiel » du wireframe.

---

_Document rédigé le 2026-02-26. Source de vérité : wireframe v3.19.2._
