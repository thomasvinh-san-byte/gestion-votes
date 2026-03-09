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
| C3 | **FK manquantes sur 3 tables** — `speech_requests`, `meeting_notifications`, `manual_actions` creees par migration 20260220 sans aucune FK (tenant_id, meeting_id, member_id) alors que schema-master les definit | `database/migrations/20260220_speech_notifications_tables.sql` | Orphelinage de donnees possible |
| C4 | **0 tests E2E** — le dossier `tests/e2e/specs/` existe mais est vide. Aucune validation du flux utilisateur complet (login > vote > resultats) | `tests/e2e/` | Regressions UI non detectees |
| C5 | **26+ routes marquees `[planned]` avec handler vide ou stub** — fonctionnalites annoncees mais non implementees | `app/routes.php` | UX trompeuse, 404/500 en production |

### ELEVE (risque de securite ou dette technique majeure)

| # | Probleme | Localisation | Impact |
|---|----------|-------------|--------|
| E1 | **`exit()` directs dans 3 controllers** — QuorumController (4x), AnalyticsController, AuditController. Contourne le pipeline d'erreur, pas de headers securite, pas de logging | `app/Controller/QuorumController.php:22,26,44,53` | Reponses non securisees |
| E2 | **Open redirect potentiel** dans EmailTrackingController — `redirect()` fait un 302 sans whitelist d'URL | `app/Controller/EmailTrackingController.php` | Phishing via lien de tracking |
| E3 | **Redis silencieusement desactive** — si Redis indisponible, le rate limiting applicatif tombe sans avertissement | `app/Core/Providers/RedisProvider.php` | Brute force non limite |
| E4 | **`meetings.paused_by`** sans FK vers `users(id)` — colonne ajoutee sans contrainte | Migration 20260219 | Integrite referentielle cassee |
| E5 | **Pas de tests** pour : rate limiter, upload fichier, WebSocket, securite headers, session fixation | `tests/` | Surfaces non couvertes |
| E6 | **Contrainte UNIQUE sur `invitations.token` toujours presente** — la colonne est maintenant NULL (tokens hashes), mais la contrainte n'a jamais ete droppee | `schema-master.sql` | Confusion, erreurs potentielles sur INSERT |
| E7 | **Pas de trigger `updated_at`** sur `meeting_roles` | Schema | Pas de tracabilite des modifications de roles |
| E8 | **Polling 5s au lieu de SSE/WebSocket** pour le temps reel — le backend a `app/WebSocket/` mais le frontend ne l'utilise pas | `core/shell.js` | Charge serveur inutile, latence UX |

### MODERE (qualite, maintenabilite)

| # | Probleme | Localisation | Impact |
|---|----------|-------------|--------|
| M1 | **Etat des motions implicite** — deduit de `(opened_at, closed_at)` sans CHECK constraint forcant la coherence. Colonne `status` redondante avec CHECK mais pas synchronisee par trigger | Table `motions` | Donnees incoherentes possibles |
| M2 | **`PermissionChecker.php` duplique `AuthMiddleware`** — deux systemes de verification de permissions coexistent | `app/Core/Security/` | Code mort, confusion |
| M3 | **Event system sous-utilise** — `AppEvent`, `VoteEvents` cables mais seul `WebSocketListener` est enregistre | `app/Event/` | Complexite sans benefice |
| M4 | **Pas de QueryBuilder** — patterns SQL repetes dans chaque repository | `app/Repository/` | Duplication, risque d'inconsistance |
| M5 | **CI ne bloque pas sur le build** — `validate` et `build` en parallele, un echec de tests n'empeche pas la publication de l'image | `.github/workflows/docker-build.yml` | Image potentiellement cassee publiee |
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

1. [ ] **Migration FK manquantes** — Creer `database/migrations/20260310_add_missing_fks.sql` :
   - `speech_requests` : FK sur tenant_id, meeting_id, member_id
   - `meeting_notifications` : FK sur tenant_id, meeting_id
   - `manual_actions` : FK sur tenant_id, meeting_id
   - `meetings.paused_by` : FK vers `users(id) ON DELETE SET NULL`
2. [ ] **Drop UNIQUE sur `invitations.token`** — ajouter dans la meme migration
3. [ ] **Trigger `updated_at` sur `meeting_roles`** — ajouter dans la meme migration
4. [ ] **Routes `[planned]`** — Pour chaque route stub :
   - Soit implementer la fonctionnalite
   - Soit retourner `api_fail('Not implemented', 501)` avec message clair
   - Soit supprimer la route du router
5. [ ] **Remplacer tous les `exit()`** dans QuorumController, AnalyticsController, AuditController par `api_fail()` ou `JsonResponse`

### Phase 2 — Securite (eleve)

6. [ ] **Open redirect** — Ajouter whitelist de domaines dans `EmailTrackingController::redirect()`
7. [ ] **Redis fallback** — Logger un WARNING quand Redis est indisponible et que le rate limiting tombe en mode fichier. Ajouter alerte dans `system_alerts`
8. [ ] **CI : ajouter `needs: validate`** sur le job `build` dans `docker-build.yml` pour empecher la publication d'une image si les tests echouent

### Phase 3 — Tests (fondamental)

9. [ ] **Tests E2E** — Implementer les specs Playwright :
   - `auth.spec.js` : login, logout, session timeout, acces refuse
   - `vote.spec.js` : flux complet de vote (operateur ouvre > votant vote > resultat)
   - `meetings.spec.js` : creation, lancement, cloture, validation
10. [ ] **Tests repositories** — Au minimum : `MeetingRepository`, `BallotRepository`, `MotionRepository` (integration avec base SQLite ou PostgreSQL de test)
11. [ ] **Tests rate limiter** — Couvrir sliding window, fallback fichier, Redis indisponible
12. [ ] **Tests upload** — MIME type, path traversal, fichier trop gros, extension interdite

### Phase 4 — Operations (production-readiness)

13. [ ] **Cron emails** — Ajouter dans `supervisord.conf` ou documenter un cron externe :
    ```
    [program:email-queue]
    command=php /var/www/bin/console email:process-queue
    autorestart=true
    startsecs=0
    ```
14. [ ] **Script backup** — Creer `bin/backup.sh` :
    - `pg_dump` avec rotation (7 jours)
    - Sauvegarde `/tmp/ag-vote/` (PV, attachments)
15. [ ] **Monitoring** — Exploiter `system_metrics` et `system_alerts` pour alerting (webhook ou email)
16. [ ] **Documentation OpenAPI** — Integrer `scripts/generate_openapi.php` dans le CI

### Phase 5 — Qualite de code (maintenabilite)

17. [ ] **Supprimer `PermissionChecker.php`** ou refactorer pour deleguer a `AuthMiddleware`
18. [ ] **Synchroniser `motions.status`** avec `(opened_at, closed_at)` via trigger ou supprimer la colonne `status` au profit du calcul
19. [ ] **Activer le WebSocket frontend** ou supprimer `app/WebSocket/` et le polling pour SSE (Server-Sent Events)
20. [ ] **Decouper `operator.htmx.html`** (89KB) en fragments HTMX charges a la demande
21. [ ] **Documenter `effective_power`** — ajouter COMMENT sur la colonne ou trigger pour default

---

## 7. Verdict global

| Dimension | Note | Commentaire |
|-----------|------|-------------|
| Architecture backend | 8/10 | Propre, bien structuree, quelques duplications |
| Securite | 8.5/10 | Excellentes fondations, quelques trous (open redirect, exit()) |
| Base de donnees | 8/10 | Complete, quelques FK manquantes post-migration |
| Frontend | 7.5/10 | Moderne (HTMX+WC), accessible, pas de temps reel vrai |
| Tests | 5/10 | Unit OK mais E2E=0, repositories=0, frontend=0 |
| DevOps/CI | 6/10 | Docker solide, CI basique, pas de backup, pas de cron email |
| Documentation | 8/10 | Abondante (37 fichiers .md), parfois redondante |
| Production-readiness | 6.5/10 | Manque cron, backup, monitoring, E2E, fix des routes planned |

**Le projet est fonctionnel et bien concu architecturalement, mais il a 3 angles morts majeurs : les tests E2E inexistants, les routes `[planned]` qui exposent des stubs en production, et l'absence d'infrastructure operationnelle (cron emails, backups, alerting).**
