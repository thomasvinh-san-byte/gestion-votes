# Topographie & Audit technique AG-VOTE

> **Date** : 2026-03-09
> **Portee** : Audit exhaustif architecture, securite, base de donnees, frontend, tests, DevOps.

---

## 1. Architecture globale

| Couche | Technologie | Maturite |
|--------|------------|----------|
| Backend | PHP 8.4, framework custom MVC, PSR-4 | Mature |
| Base de donnees | PostgreSQL 16, 40 tables, 78 index | Solide |
| Cache/Queue | Redis 7 (optionnel, fallback fichier) | Fonctionnel |
| Frontend | HTMX + Web Components vanilla, 0 framework | Mature |
| Infra | Docker (Alpine), Nginx + PHP-FPM, supervisord | Production-ready |
| CI/CD | GitHub Actions (test + build + push GHCR) | Basique |
| Tests | PHPUnit (62 unit, 2 integration), Playwright (vide) | Incomplet |

### Volumetrie du code (hors vendor/)

| Type | Fichiers | Lignes estimees |
|------|----------|-----------------|
| PHP (app + api) | ~350 | ~25 000 |
| JavaScript | 32 | ~15 000 |
| SQL (schema + migrations + seeds) | 27 | ~3 500 |
| CSS | 20 | ~5 000 |
| Tests PHP | 64 | ~37 000 |
| Documentation | 37 | ~8 000 |

---

## 2. Ce qui fonctionne bien

### Architecture

- Separation propre Controllers (39) / Repositories (27+traits) / Services (18).
- Multi-tenancy systematique (`tenant_id` sur toutes les requetes).
- RBAC a deux niveaux (systeme : admin/operator/auditor/viewer + seance : president/assessor/voter).
- Machine a etats meetings complete (draft > scheduled > frozen > live > paused > closed > validated > archived).
- Requetes SQL 100% parametrees PDO — aucune injection SQL detectee.

### Securite

- CSRF : Synchronizer Token OWASP, `hash_equals()`, sliding window 30 min.
- Auth : `password_hash(PASSWORD_DEFAULT)` + rehash automatique + dummy hash anti-enumeration.
- API keys : HMAC-SHA256 avant stockage.
- Sessions : HttpOnly, SameSite=Lax, revalidation toutes les 60s, timeout 30 min.
- Rate limiting : double couche (Nginx + Redis/fichier applicatif).
- Headers securite : CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy.
- Audit : chaine de hash SHA256 immutable, trigger PostgreSQL empechant DELETE/UPDATE.
- Vote tokens : usage unique, cryptographiquement aleatoires, expiration 1h.

### Frontend

- 20 Web Components custom avec Shadow DOM (isolation XSS).
- `escapeHtml()` systematique dans les templates JS.
- PWA complete (manifest, service worker, icones, mode offline).
- Dark mode avec detection `prefers-color-scheme`.
- 87 media queries, mobile-first.
- 641+ attributs ARIA, navigation clavier, skip links.

### Base de donnees

- Schema master idempotent, 40 tables, enums types.
- Index partiels bien cibles (meetings par statut, tokens non utilises, proxies actifs).
- Contraintes CHECK sur ballots, motions, membres.
- FK avec CASCADE/SET NULL appropries.
- Seeds idempotents avec UUIDs deterministes.

---

## 3. Problemes identifies — classes par gravite

### CRITIQUE (bloque le fonctionnement ou la fiabilite)

| # | Probleme | Localisation | Impact |
|---|----------|-------------|--------|
| C1 | **`OfficialResultsService::computeOfficialTallies()` ecrit des valeurs invalides en base** — le chemin eVOTE passe `no_votes`, `no_quorum`, `no_policy` directement dans `motions.decision` | `app/Services/OfficialResultsService.php:319` | Corrige (commit c5bc8c6) |
| C2 | **Migration 20260218 echoue** sur bases existantes — le CHECK constraint est applique sans normaliser les donnees | `database/migrations/20260218_security_hardening.sql:101-105` | Corrige (commit c5bc8c6) |
| C3 | ~~**FK manquantes sur 3 tables**~~ | `database/migrations/20260310_add_missing_fks.sql` | **Corrige** — FK ajoutees sur speech_requests, meeting_notifications, manual_actions, meeting_validation_state |
| C4 | **0 tests E2E** — le dossier `tests/e2e/specs/` existe mais est vide. Aucune validation du flux utilisateur complet (login > vote > resultats) | `tests/e2e/` | Regressions UI non detectees |
| C5 | ~~**Routes `[planned]` avec handler stub**~~ — en realite toutes les 15 routes sont **implementees**. Annotations trompeuses retirees | `app/routes.php` | **Corrige** — annotations retirees |

### ELEVE (risque de securite ou dette technique majeure)

| # | Probleme | Localisation | Impact |
|---|----------|-------------|--------|
| E1 | ~~**`exit()` directs dans 7 controllers**~~ — 20 occurrences remplacees par `return;` | 7 controllers | **Corrige** |
| E2 | ~~**Open redirect potentiel**~~ — en realite deja protege par whitelist host (`app_url`) | `EmailTrackingController.php` | **Faux positif** |
| E3 | ~~**Redis silencieusement desactive**~~ — warning `error_log()` ajoute dans `RateLimiter::useRedis()` | `app/Core/Security/RateLimiter.php` | **Corrige** |
| E4 | ~~**`meetings.paused_by` sans FK**~~ | `database/migrations/20260310_add_missing_fks.sql` | **Corrige** |
| E5 | **Pas de tests** pour : rate limiter, upload fichier, WebSocket, securite headers, session fixation | `tests/` | Surfaces non couvertes |
| E6 | ~~**UNIQUE sur `invitations.token`**~~ — constraint droppee dans migration 20260310 | `database/migrations/20260310_add_missing_fks.sql` | **Corrige** |
| E7 | ~~**Pas de trigger `updated_at` sur `meeting_roles`**~~ — trigger + colonne ajoutes | `database/migrations/20260310_add_missing_fks.sql` | **Corrige** |
| E8 | **Polling 5s au lieu de SSE/WebSocket** pour le temps reel — le backend a `app/WebSocket/` mais le frontend ne l'utilise pas | `core/shell.js` | Charge serveur inutile, latence UX |

### MODERE (qualite, maintenabilite)

| # | Probleme | Localisation | Impact |
|---|----------|-------------|--------|
| M1 | **Etat des motions implicite** — deduit de `(opened_at, closed_at)` sans CHECK constraint forcant la coherence. Colonne `status` redondante avec CHECK mais pas synchronisee par trigger | Table `motions` | Donnees incoherentes possibles |
| M2 | **`PermissionChecker.php` duplique `AuthMiddleware`** — deux systemes de verification de permissions coexistent | `app/Core/Security/` | Code mort, confusion |
| M3 | **Event system sous-utilise** — `AppEvent`, `VoteEvents` cables mais seul `WebSocketListener` est enregistre | `app/Event/` | Complexite sans benefice |
| M4 | **Pas de QueryBuilder** — patterns SQL repetes dans chaque repository | `app/Repository/` | Duplication, risque d'inconsistance |
| M5 | ~~**CI ne bloque pas sur le build**~~ — `needs: validate` ajoute au job `build` | `.github/workflows/docker-build.yml` | **Corrige** |
| M6 | **`effective_power` nullable** sur `attendances` — signification implicite (NULL = utiliser `voting_power` du membre) sans documentation ni trigger | Table `attendances` | Ambiguite metier |
| M7 | **Styles inline** dans `partials/sidebar.html` | `public/partials/` | Maintenabilite CSS |
| M8 | **89KB pour `operator.htmx.html`** — page la plus lourde, 128 refs SVG, tout en un seul fichier | `public/operator.htmx.html` | Performance mobile |
| M9 | **Cron email/reminders non documente** — `EmailQueueService::processQueue()` et `processReminders()` existent mais aucun cron/supervisor configure | `deploy/supervisord.conf` | Emails jamais envoyes en prod |
| M10 | **Pas de backup documente** — aucune procedure pg_dump/restore ni script de sauvegarde | `deploy/`, `bin/` | Perte de donnees |

### BAS (ameliorations souhaitables)

| # | Probleme | Localisation |
|---|----------|-------------|
| B1 | Session timeout hardcode 30 min (non configurable via env) | `AuthMiddleware.php:44` |
| B2 | Pas de SRI (Subresource Integrity) sur Google Fonts | Pages HTML |
| B3 | `style-src 'unsafe-inline'` dans CSP | `SecurityProvider.php` |
| B4 | Pas de load/performance tests | `tests/` |
| B5 | Pas de documentation OpenAPI auto-generee (script existe mais non integre au CI) | `scripts/generate_openapi.php` |
| B6 | Timing leak mineur sur `findByEmailGlobal()` (requete synchrone) | `AuthMiddleware` |
| B7 | Bloc `if ($method === 'GET') {}` vide dans AdminController | `AdminController.php:26-29` |

---

## 4. Routes `[planned]` non implementees

| Route | Controller | Fonctionnalite |
|-------|-----------|----------------|
| `meeting_late_rules` | AgendaController | Regles de retard |
| `attendance_present_from` | AttendancesController | Heure d'arrivee |
| `motion_tally` | MotionsController | Decompte manuel |
| `degraded_tally` | MotionsController | Mode degrade |
| `paper_ballot_redeem` | BallotsController | Bulletins papier |
| `operator_open_vote` | OperatorController | Ouverture directe |
| `meeting_consolidate` | MeetingWorkflowController | Consolidation post-seance |
| `invitations_create` | InvitationsController | Invitation manuelle |
| `invitations_list` | InvitationsController | Liste invitations |
| `proxies_delete` | ProxiesController | Suppression individuelle |
| `email_preview` | EmailController | Previsualisation email |
| `motions_import_csv` | ImportController | Import motions CSV |
| `motions_import_xlsx` | ImportController | Import motions XLSX |
| `reminders_*` | ReminderController | Programmation rappels |

---

## 5. Couverture tests

| Composant | Unit | Integration | E2E | Verdict |
|-----------|------|-------------|-----|---------|
| Auth/CSRF/Sessions | 3 fichiers | 0 | 0 | Partiel |
| VoteEngine/Quorum | 4 fichiers | 0 | 0 | Bien couvert |
| Controllers (39) | ~25 fichiers | 2 | 0 | 14 non testes |
| Repositories | 0 | 0 | 0 | **Zero couverture** |
| Services metier | ~8 fichiers | 0 | 0 | Partiel |
| Rate Limiter | 0 | 0 | 0 | **Non teste** |
| File Upload | 0 | 0 | 0 | **Non teste** |
| WebSocket | 0 | 0 | 0 | **Non teste** |
| Frontend (JS) | 0 | 0 | 0 | **Zero** |

---

## 6. Marche a suivre — Plan d'action priorise

### Phase 1 — Correctifs critiques (bloquants)

1. [x] **Migration FK manquantes** — `database/migrations/20260310_add_missing_fks.sql` :
   - `speech_requests` : FK sur tenant_id, meeting_id, member_id
   - `meeting_notifications` : FK sur tenant_id, meeting_id
   - `meeting_validation_state` : FK sur meeting_id, tenant_id
   - `manual_actions` : FK sur tenant_id, meeting_id, motion_id
   - `meetings.paused_by` : FK vers `users(id) ON DELETE SET NULL`
2. [x] **Drop UNIQUE sur `invitations.token`** — dans la meme migration
3. [x] **Trigger `updated_at` sur `meeting_roles`** — dans la meme migration
4. [x] **Routes `[planned]`** — Les 15 routes etaient en realite toutes implementees. Annotations `[planned]` retirees de `routes.php`
5. [x] **Remplacer tous les `exit()`** — 20 occurrences dans 7 controllers remplacees par `return;`

### Phase 2 — Securite (eleve)

6. [x] **Open redirect** — Faux positif : `EmailTrackingController::redirect()` a deja une whitelist par host (`app_url`)
7. [x] **Redis fallback** — Warning `error_log()` ajoute dans `RateLimiter::useRedis()` (log unique par requete)
8. [x] **CI : `needs: validate`** ajoute au job `build` dans `docker-build.yml`

### Phase 3 — Tests (fondamental)

9. [x] **Tests E2E** — Specs Playwright implementees :
   - `auth.spec.js` : login page, invalid creds, valid login, logout (4 tests)
   - `vote.spec.js` : flux vote complet, API validation, securite, confirmation UI (17 tests)
   - `meetings.spec.js` : liste, navigation operateur, creation, onglets (4 tests)
   - `accessibility.spec.js` : formulaires, headings, clavier, ARIA, landmarks (6 tests)
10. [x] **Tests repositories** — `tests/Integration/RepositoryTest.php` : MeetingRepository (7 tests), BallotRepository (3 tests), MotionRepository (1 test), AbstractRepository (2 tests). Skipped sans PostgreSQL.
11. [x] **Tests rate limiter** — `tests/Unit/RateLimiterTest.php` elargi : strict mode, sliding window, edge cases, haute concurrence (24 tests total)
12. [x] **Tests upload** — `tests/Unit/UploadSecurityTest.php` : MIME types (21 variants), extensions (21 variants), path traversal (11 variants), taille fichier (11 cas), verification source controller (3 tests)

### Phase 4 — Operations (production-readiness)

13. [x] **Cron emails** — Workers ajoutes dans `deploy/supervisord.conf` : `email-queue` (toutes les 60s) + `ratelimit-cleanup` (toutes les 30 min)
14. [x] **Script backup** — `bin/backup.sh` cree : pg_dump compresse, archivage uploads, rotation configurable (defaut 7 jours), mode dry-run
15. [ ] **Monitoring** — Exploiter `system_metrics` et `system_alerts` pour alerting (webhook ou email)
16. [ ] **Documentation OpenAPI** — Integrer `scripts/generate_openapi.php` dans le CI

### Phase 5 — Qualite de code (maintenabilite)

17. [x] **`PermissionChecker.php` deprecie** — Analyse : classe non utilisee en production (0 references hors tests). `@deprecated` ajoute. AuthMiddleware est l'unique systeme de permissions actif.
18. [x] **`motions.status` deja resolu** — La colonne a ete supprimee dans migration `20260220_drop_phantom_columns.sql`. L'etat est derive de `opened_at`/`closed_at` via CASE dans les requetes SQL.
19. [ ] **Activer le WebSocket frontend** ou supprimer `app/WebSocket/` et le polling pour SSE (Server-Sent Events)
20. [ ] **Decouper `operator.htmx.html`** (89KB) en fragments HTMX charges a la demande
21. [x] **`effective_power` documente** — Migration `20260310_document_columns.sql` : COMMENT ON COLUMN sur `attendances.effective_power`, COMMENT ON TABLE `motions`, COMMENT ON COLUMN `audit_log.previous_hash`

---

## 7. Verdict global

| Dimension | Note initiale | Note apres corrections | Commentaire |
|-----------|---------------|----------------------|-------------|
| Architecture backend | 8/10 | 8.5/10 | `exit()` remplaces, PermissionChecker deprecie |
| Securite | 8.5/10 | 9/10 | Open redirect = faux positif, Redis warning ajoute, CI bloque sur tests |
| Base de donnees | 8/10 | 9/10 | FK ajoutees, colonnes documentees, migration idempotente |
| Frontend | 7.5/10 | 7.5/10 | Inchange — WebSocket frontend reste a activer |
| Tests | 5/10 | 7/10 | E2E (31 specs), rate limiter (24), upload (67), repositories (13) |
| DevOps/CI | 6/10 | 8/10 | Supervisord email+cleanup, backup.sh, CI needs:validate |
| Documentation | 8/10 | 8.5/10 | Audit technique ajoute, COMMENT SQL sur colonnes ambigues |
| Production-readiness | 6.5/10 | 8/10 | Cron emails, backups, E2E, routes corrigees |

**Apres corrections des phases 1 a 5 : le projet passe d'un etat "fonctionnel avec angles morts" a "quasi production-ready". Restent : activation WebSocket frontend (ou SSE), decoupage operator.htmx.html, monitoring/alerting, et integration OpenAPI dans le CI.**
