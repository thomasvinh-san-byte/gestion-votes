# AG-VOTE — Roadmap Production

> Dernière mise à jour : 18 février 2026
> Consolidation des plans et audits précédents en un seul document actionnable.

---

## Contexte

Le backend est **production-ready** : architecture solide, sécurité en profondeur (RBAC, CSRF, rate-limit, audit immutable), 265+ tests unitaires passants, validation centralisée sur les routes critiques.

Les chantiers restants concernent : **bugs de déploiement**, **UX votant**, **cohérence design** et **accessibilité**.

---

## Pré-requis déploiement (jour J)

Configurations serveur, pas du code à écrire.

| Action | Fichier/Lieu | Notes |
|--------|-------------|-------|
| `APP_SECRET` fort (64 chars) | `.env` | `php -r "echo bin2hex(random_bytes(32));"` |
| `APP_AUTH_ENABLED=1` | `.env` | Déjà la valeur par défaut dans `.env.example` |
| `CSRF_ENABLED=1` | `.env` | Idem |
| `RATE_LIMIT_ENABLED=1` | `.env` | Idem |
| HTTPS obligatoire | Reverse proxy (nginx/Apache) | Redirection 301 HTTP → HTTPS au niveau serveur |
| HSTS | Automatique | Déjà géré par `SecurityHeaders.php` si HTTPS actif |
| PostgreSQL 16+ | Serveur | Schema : `database/schema-master.sql` |

---

## Phase 1 — Bugs déploiement (bloquant)

**Effort estimé : 0.5 jour**

### 1.1 `.dockerignore` exclut le dossier `docs/`

**Statut : bug confirmé** — cause du « Document non trouvé : ANALYTICS_ETHICS.md » sur Render.

Le `.dockerignore` contient :
```
docs/
*.md
```

Le dossier `docs/` n'est jamais copié dans l'image Docker. Or `doc_content.php` lit les fichiers depuis `dirname(__DIR__, 3) . '/docs'` (= `/var/www/docs` dans le conteneur).

**Action :**
- Retirer `docs/` et `*.md` du `.dockerignore`
- Alternativement, garder l'exclusion mais ajouter des exceptions : `!docs/` et `!README.md`
- Redéployer sur Render

**Fichiers :** `.dockerignore`

---

## Phase 2 — Auto-identification du votant

**Effort estimé : 1-2 jours**

### 2.1 Problème actuel

Sur la page vote (`vote.htmx.html`), le votant doit **manuellement chercher son nom** dans un dropdown (`ag-searchable-select`). Il existe un `autoSelectMember()` (vote.js:460-492) qui tente un match par nom/email, mais c'est du best-effort fragile (comparaison `includes()` sur des chaînes).

### 2.2 Solution : liaison `user_id` → `member_id`

La table `members` possède déjà une colonne `user_id uuid REFERENCES users(id)`. Cette relation est optionnelle mais jamais exploitée côté API.

**Action backend :**
- Enrichir `/api/v1/whoami.php` : quand `user_id` matche un member dans le tenant courant, retourner `member_id` dans la réponse
- Ou créer un endpoint `/api/v1/my_member.php?meeting_id=xxx` qui retourne le member lié au user authentifié pour cette séance

```php
// Dans whoami.php, après les meeting_roles :
$memberRepo = new \AgVote\Repository\MemberRepository();
$linkedMember = $memberRepo->findByUserId($user['id'], $user['tenant_id']);
// Ajouter dans la réponse :
'member' => $linkedMember ? ['id' => $linkedMember['id'], 'full_name' => $linkedMember['full_name']] : null,
```

**Action frontend (`vote.js`) :**
- Si `window.Auth.user.member` existe (non null), sélectionner directement ce `member.id` dans le dropdown
- Supprimer le fallback `autoSelectMember()` par matching de nom (fragile)
- Conserver le localStorage comme fallback si pas authentifié (mode démo)

**Action admin (`members.htmx.html` ou `admin.htmx.html`) :**
- Permettre de lier un user existant à un member (assigner `user_id` sur la fiche member)
- Ou le faire automatiquement quand l'email du member matche l'email d'un user

**Fichiers :**
- `public/api/v1/whoami.php`
- `app/Repository/MemberRepository.php` (ajouter `findByUserId()`)
- `public/assets/js/pages/vote.js` (simplifier `loadMembers()` + `autoSelectMember()`)
- `public/vote.htmx.html` (masquer le dropdown si auto-identifié, afficher le nom directement)

---

## Phase 3 — Cohérence design : retirer le wizard hors opérateur

**Effort estimé : 1-2 jours**

### 3.1 Problème

Le `session-wizard.js` injecte une barre de progression « Diligent-style » (6 étapes) sur les pages :
- `members.htmx.html` (ligne 286)
- `validate.htmx.html` (ligne 225)
- `archives.htmx.html` (ligne 189)

Ces pages sont des **CRUD autonomes** (gestion membres, validation, archives). Le wizard n'a de sens que dans le contexte de la **console opérateur** qui orchestre la séance.

Les pages `admin.htmx.html` et `meetings.htmx.html` n'ont déjà pas de wizard — ce qui crée une **incohérence** : certaines pages CRUD ont un wizard, d'autres non.

### 3.2 Solution

**Principe :** Le wizard = la console opérateur. Les autres pages = CRUD pur.

**Action :**
- Retirer `<script src="/assets/js/services/session-wizard.js">` de :
  - `members.htmx.html`
  - `validate.htmx.html`
  - `archives.htmx.html`
- Conserver le contexte meeting (MeetingContext) sur ces pages — le meeting sélectionné reste accessible sans wizard
- S'assurer que chaque page a sa propre sélection de séance si nécessaire (dropdown simple dans le topbar ou un filtre)
- Vérifier que la navigation sidebar ne dépend pas du wizard pour le highlighting
- Optionnel : nettoyer `session-wizard.js` des références aux pages retirées (`PAGE_STEP_MAP`)

**Fichiers :**
- `public/members.htmx.html` — retirer le script wizard
- `public/validate.htmx.html` — retirer le script wizard
- `public/archives.htmx.html` — retirer le script wizard
- `public/assets/js/services/session-wizard.js` — nettoyer `PAGE_STEP_MAP`
- Potentiellement : `public/assets/js/core/shell.js` si la sidebar dépend du wizard

---

## Phase 4 — Accessibilité (WCAG AA)

**Effort estimé : 1-2 jours**

### 4.1 Focus trap sur les modals

**Statut : manquant**
Les modals (`shared.js`) ont `role="dialog"` et `aria-modal="true"` mais aucun piège de focus.

**Action :**
- Ajouter une fonction `trapFocus(modalElement)` dans `shared.js`
- Piéger le focus entre le premier et le dernier élément focusable
- Restaurer le focus sur l'élément déclencheur à la fermeture
- Gérer Escape pour fermer

**Fichiers :** `public/assets/js/core/shared.js`

### 4.2 ARIA sur les formulaires

**Statut : partiel**
`aria-live` en place (13 fichiers), skip-links implémentés (10 pages). Reste à vérifier les `<input>` dans les modals.

**Action :**
- Auditer les formulaires dans les modals (operator, admin, members)
- Ajouter `aria-label` ou `for`/`id` sur les inputs orphelins

**Fichiers :** `public/operator.htmx.html`, `public/admin.htmx.html`, `public/members.htmx.html`

---

## Phase 5 — CSS consolidation (maintenance)

**Effort estimé : 1-2 jours**

### 5.1 Dédupliquer design-system.css / app.css

`design-system.css` définit les fondations (tokens, base elements). `app.css` redéfinit certaines règles (`.form-input`, `.table`, etc.).

**Action :**
- Identifier les règles dupliquées entre les deux fichiers
- Garder les définitions dans `design-system.css` (source de vérité)
- Dans `app.css`, ne garder que les surcharges spécifiques aux pages
- Vérifier visuellement les pages après nettoyage

**Fichiers :** `public/assets/css/design-system.css`, `public/assets/css/app.css`

---

## Phase 6 — Nice to have (post-lancement)

Ces items ne bloquent pas la production mais améliorent l'expérience.

| Action | Impact | Effort | Priorité |
|--------|--------|--------|----------|
| `prefers-reduced-motion` | Accessibilité | 0.5j | Moyenne |
| Tests E2E (Playwright) | Qualité/régression | 1 sem | Moyenne |
| Cache Redis/Memcached | Performance | 3j | Basse |
| WebSockets natifs (remplacer polling) | Performance | 1 sem | Basse |

---

## Ordre d'exécution recommandé

```
Phase 1 (dockerignore)     ← 30 min, débloque la doc sur Render
    ↓
Phase 2 (auto-id votant)   ← Fonctionnel, améliore l'UX du chemin critique
    ↓
Phase 3 (retrait wizard)   ← Design, cohérence navigation
    ↓
Phase 4 (accessibilité)    ← WCAG AA, qualité
    ↓
Phase 5 (CSS)              ← Maintenance, pas de changement visible
    ↓
Phase 6 (nice to have)     ← Post-lancement
```

---

## Ce qui est terminé (référence)

Pour mémoire, les chantiers suivants sont **complétés et n'apparaissent plus dans ce plan** :

- Namespaces PSR-4 `AgVote\*` sur tout le backend
- InputValidator + ValidationSchemas sur les routes critiques
- Try/catch + ErrorDictionary sur les routes transactionnelles
- RBAC sur les 4 routes non protégées (quorum_policies, vote_policies, meeting_status, quorum_status)
- Suppression fuite debug (attendances.php)
- MeetingContext singleton (plus de getMeetingId dupliqué)
- AgToast unifié (setNotif délègue à AgToast)
- WebSocket/polling sans race condition
- Legacy CSS supprimé (.btn.primary → .btn-primary, .h1/.h2/.tiny supprimés)
- console.log gardés par `window.AG_DEBUG`
- Tests unitaires : VoteEngine, QuorumEngine, InputValidator, TenantIsolation, AuthMiddleware, MailerService
- Commentaires en anglais, JSDoc/PHPDoc complétés
- Linter configs (.eslintrc, .stylelintrc, .editorconfig, .php-cs-fixer)

---

## Documents archivés

Les documents suivants sont conservés comme historique mais ne contiennent plus de tâches actives :

| Document | Rôle | Statut |
|----------|------|--------|
| `PLAN.md` (racine) | Plan durcissement API (Phases A-E) | Terminé |
| `docs/dev/PLAN_HARMONISATION.md` | Plan harmonisation (Phases 1-6) | Terminé |
| `docs/dev/AUDIT_RAPPORT.md` | Audit complet du 4 fév 2026 | Référence |
| `docs/AUDIT_REPORT_2026-02-06.md` | Audit exécutif du 6 fév 2026 | Référence |
| `docs/dev/CONFORMITE_CDC.md` | Conformité cahier des charges | Référence |
