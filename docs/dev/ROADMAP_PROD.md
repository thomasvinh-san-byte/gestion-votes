# AG-VOTE — Roadmap Production

> Dernière mise à jour : 18 février 2026
> Consolidation des plans et audits précédents en un seul document actionnable.

---

## Contexte

Le backend est **production-ready** : architecture solide, sécurité en profondeur (RBAC, CSRF, rate-limit, audit immutable), 265+ tests unitaires passants, validation centralisée sur les routes critiques.

Les chantiers restants concernent uniquement le **frontend** (accessibilité, CSS) et la **configuration de déploiement**.

---

## Pré-requis déploiement (jour J)

Ces éléments sont des configurations serveur, pas du code à écrire.

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

## Phase 1 — Accessibilité (bloquant WCAG AA)

**Effort estimé : 2-3 jours**

### 1.1 Focus trap sur les modals

**Statut : manquant**
Les modals (`shared.js`) ont `role="dialog"` et `aria-modal="true"` mais aucun piège de focus. L'utilisateur peut tab hors du modal.

**Action :**
- Ajouter une fonction `trapFocus(modalElement)` dans `shared.js`
- Piéger le focus entre le premier et le dernier élément focusable
- Restaurer le focus sur l'élément déclencheur à la fermeture
- Gérer Escape pour fermer

**Fichiers :** `public/assets/js/core/shared.js`

### 1.2 ARIA sur les formulaires

**Statut : partiel**
Les `aria-live` sont en place (13 fichiers), les skip-links sont implémentés (10 pages). Il reste à vérifier que chaque `<input>` dans les modals a un `aria-label` ou un `<label>` associé.

**Action :**
- Auditer les formulaires dans les modals (operator, admin, members)
- Ajouter `aria-label` ou `for`/`id` sur les inputs orphelins

**Fichiers :** `public/operator.htmx.html`, `public/admin.htmx.html`, `public/members.htmx.html`

---

## Phase 2 — CSS consolidation (maintenance)

**Effort estimé : 1-2 jours**

### 2.1 Dédupliquer design-system.css / app.css

**Statut : duplication existante**
`design-system.css` définit les fondations (tokens, base elements). `app.css` redéfinit certaines règles (`.form-input`, `.table`, etc.).

**Action :**
- Identifier les règles dupliquées entre les deux fichiers
- Garder les définitions dans `design-system.css` (source de vérité)
- Dans `app.css`, ne garder que les surcharges spécifiques aux pages
- Vérifier visuellement les pages après nettoyage

**Fichiers :** `public/assets/css/design-system.css`, `public/assets/css/app.css`

---

## Phase 3 — Nice to have (post-lancement)

Ces items ne bloquent pas la production mais améliorent l'expérience.

| Action | Impact | Effort | Priorité |
|--------|--------|--------|----------|
| `prefers-reduced-motion` | Accessibilité | 0.5j | Moyenne |
| Tests E2E (Playwright) | Qualité/régression | 1 sem | Moyenne |
| Cache Redis/Memcached | Performance | 3j | Basse |
| WebSockets natifs (remplacer polling) | Performance | 1 sem | Basse |

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
