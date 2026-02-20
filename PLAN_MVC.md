# Plan de migration MVC — AG-VOTE (v2, post-audit)

## Contexte

**État actuel** : 142 endpoints procéduraux, 28 repositories (propres, 0 dépendances croisées), 18 services (mix static/instance), 0 routeur, routing fichier via nginx, 82 URLs hardcodées dans le frontend (HTMX + vanilla JS).

**Objectif** : Consolider 142 fichiers en ~21 contrôleurs MVC, simplifier les relations entre couches, améliorer la testabilité.

**Contrainte** : Migration incrémentale, rétrocompatibilité totale pendant la transition (les anciennes URLs continuent de fonctionner via proxys, maintenues minimum 4-6 semaines en production).

---

## Phase 1 — Micro-routeur + infrastructure contrôleur

**But** : Poser les fondations sans toucher aux endpoints existants.

### 1.1 Installer FastRoute
- `composer require nikic/fast-route` (compatible PHP 8.4)
- Créer `app/Router.php` qui mappe `METHOD /api/v1/resource/action` → `Controller::method()`
- Créer un point d'entrée unique `public/api/v1/index.php` qui utilise le routeur
- Configurer nginx : les requêtes sans `.php` passent par `index.php`, les `.php` existants continuent de fonctionner en parallèle

### 1.2 Créer `app/Controller/AbstractController.php`
- Accès aux helpers existants : `api_ok()`, `api_fail()`, `api_require_role()`, `api_current_tenant_id()`
- Injection de dépendances via constructeur (repos + services)
- Méthode protégée `input()` → retourne les données parsées via `api_request()`
- Méthode protégée `guard()` → vérifie meeting non validé, etc.
- Méthode protégée `transaction(callable $fn)` → wrapper `api_transaction()`

### 1.3 Tests d'infrastructure
- Test unitaire : le routeur résout les routes correctement
- Test unitaire : AbstractController expose les helpers

**Fichiers créés** : 3 (`Router.php`, `AbstractController.php`, `index.php`)
**Fichiers modifiés** : 1 (config nginx)
**Risque** : Faible (aucun endpoint existant modifié)
**Critère go/no-go** : les tests passent, les endpoints `.php` existants fonctionnent toujours

---

## Phase 2 — Migration pilote : SpeechController (9 endpoints → 1 contrôleur)

**But** : Valider le pattern sur le groupe le plus simple (8/9 délèguent purement à SpeechService, `speech_grant.php` a un accès SpeechRepository mineur).

### 2.1 Créer `app/Controller/SpeechController.php`
Consolider 9 fichiers :

| Ancien fichier | Nouvelle route | Méthode |
|---|---|---|
| `speech_request.php` | `POST /speeches/request` | `request()` |
| `speech_grant.php` | `POST /speeches/grant` | `grant()` |
| `speech_end.php` | `POST /speeches/end` | `end()` |
| `speech_cancel.php` | `POST /speeches/cancel` | `cancel()` |
| `speech_clear.php` | `POST /speeches/clear` | `clear()` |
| `speech_next.php` | `POST /speeches/next` | `next()` |
| `speech_queue.php` | `GET /speeches/queue` | `queue()` |
| `speech_current.php` | `GET /speeches/current` | `current()` |
| `speech_my_status.php` | `GET /speeches/my-status` | `myStatus()` |

Dépendances : SpeechService, SpeechRepository

### 2.2 Proxy les anciens fichiers
Chaque ancien `.php` devient un proxy de 3 lignes :
```php
require __DIR__ . '/../../../app/api.php';
(new \AgVote\Controller\SpeechController())->request();
```

### 2.3 Tests
- Test unitaire du contrôleur avec SpeechService mocké
- Vérifier que les anciennes URLs fonctionnent toujours

**Fichiers créés** : 1 contrôleur
**Fichiers modifiés** : 9 (proxyfiés)
**Lignes nettes** : ~-130
**Critère go/no-go** : les 9 anciens URLs retournent les mêmes réponses qu'avant

---

## Phase 3 — Contrôleurs à logique simple (6 contrôleurs, 27 endpoints)

**But** : Migrer les groupes qui délèguent principalement aux repos/services sans logique complexe.

### 3.1 `AuthController` (5 endpoints)
- `auth_login.php`, `auth_logout.php`, `auth_csrf.php`, `whoami.php`, `ping.php`
- Deps : AuthMiddleware

### 3.2 `DeviceController` (6 endpoints)
- `devices_list.php`, `device_block.php`, `device_unblock.php`, `device_kick.php`, `device_heartbeat.php`, `projector_state.php`
- Deps : DeviceRepository

### 3.3 `InvitationsController` (6 endpoints)
- `invitations_create.php`, `invitations_list.php`, `invitations_redeem.php`, `invitations_schedule.php`, `invitations_send_bulk.php`, `invitations_stats.php`
- Deps : InvitationRepository

### 3.4 `AgendasController` (2 endpoints)
- `agendas.php`, `agendas_for_meeting.php`
- Deps : AgendaRepository

### 3.5 `DocsController` (2 endpoints)
- `doc_index.php`, `doc_content.php`
- Deps : aucune (lecture fichiers statiques)

### 3.6 `DashboardController` (6 endpoints)
- `dashboard.php`, `quorum_card.php`, `quorum_status.php`, `wizard_status.php`, `presidents.php`, `trust_checks.php`
- Deps : MeetingRepository, AttendanceRepository, PolicyRepository

**Fichiers créés** : 6 contrôleurs
**Fichiers proxyfiés** : 27
**Lignes nettes** : ~-400

---

## Phase 3.5 — Extraction des fonctions procédurales (prérequis Phase 4)

**But** : Extraire les 38 fonctions inline de 11 fichiers vers des services dédiés. Bloquant pour Phase 4.

### Services à créer (5 nouveaux)

| Fichier source | Fonctions | Service cible |
|---|---|---|
| `analytics.php` | 8 | **`AnalyticsService`** (nouveau) |
| `reports_aggregate.php` | 2 | **`ReportsService`** (nouveau) |
| `member_groups.php` | 4 | **`MemberGroupService`** (nouveau) |
| `member_group_assignments.php` | 4 | fusionner dans `MemberGroupService` |
| `export_templates.php` | 4 | **`ExportTemplateService`** (nouveau) |
| `reminders.php` | 3 | **`ReminderService`** (nouveau) |

### Services existants à compléter (2)

| Fichier source | Fonctions | Service cible |
|---|---|---|
| `meeting_report.php` | 6 (`h()`, `decisionLabel()`, etc.) | compléter `MeetingReportService` |
| `email_templates.php` | 4 | compléter `EmailTemplateService` |

### Cas mineurs (intégrés directement dans les contrôleurs)

| Fichier source | Fonctions | Cible |
|---|---|---|
| `attendances_import_csv.php` | 1 | méthode privée dans `AttendancesController` |
| `admin_system_status.php` | 1 | méthode privée dans `AdminController` |
| `email_pixel.php` | 1 | méthode privée dans `EmailController` |

**Fichiers créés** : 5 services
**Fichiers modifiés** : 11 (extraction des fonctions)
**Tests** : unitaires pour chaque nouveau service
**Critère go/no-go** : les endpoints existants fonctionnent identiquement après extraction

---

## Phase 4 — Contrôleurs métier principaux (7 contrôleurs, ~50 endpoints)

**But** : Migrer le cœur de métier. Phase la plus importante.

### 4.1 `MembersController` (5 endpoints)
- `members.php` (CRUD GET/POST/PATCH/PUT/DELETE), `members_import_csv.php`, `members_import_xlsx.php`, `members_export.php`, `members_export_csv.php`
- Deps : MemberRepository, ImportService

### 4.2 `MemberGroupsController` (2 endpoints) — contrôleur séparé
- `member_groups.php` (GET/POST/PUT/DELETE), `member_group_assignments.php` (GET/POST/DELETE)
- Deps : MemberGroupRepository, MemberGroupService
- Note : 8 fonctions inline (extraites en Phase 3.5)

### 4.3 `AttendancesController` (7 endpoints)
- `attendances.php`, `attendances_bulk.php`, `attendances_upsert.php`, `attendance_present_from.php`, `attendances_import_csv.php`, `attendances_import_xlsx.php`, `attendance_export.php`
- Deps : AttendanceRepository, AttendancesService, ImportService

### 4.4 `ProxiesController` (5 endpoints)
- `proxies.php`, `proxies_upsert.php`, `proxies_delete.php`, `proxies_import_csv.php`, `proxies_import_xlsx.php`
- Deps : ProxyRepository, ProxiesService, ImportService

### 4.5 `BallotsController` (9 endpoints)
- `ballots.php`, `ballots_cast.php`, `ballots_cancel.php`, `ballots_result.php`, `manual_vote.php`, `vote_tokens_generate.php`, `degraded_tally.php`, `paper_ballot_redeem.php`, `vote_incident.php`
- Deps : BallotRepository, BallotsService, VoteTokenService, MeetingRepository

### 4.6 `MotionsController` (12 endpoints)
- `motions.php`, `motions_for_meeting.php`, `motions_open.php`, `motions_close.php`, `motion_create_simple.php`, `motion_delete.php`, `motion_reorder.php`, `motion_tally.php`, `current_motion.php`, `motions_import_csv.php`, `motions_import_xlsx.php`, `motions_export.php`
- Deps : MotionRepository, OfficialResultsService, VoteEngine, ImportService

### 4.7 `EmailController` (4 endpoints)
- `email_templates.php` (GET/POST/PUT/DELETE), `email_templates_preview.php`, `email_pixel.php`, `email_redirect.php`
- Deps : EmailTemplateService, EmailQueueService, EmailEventRepository

**Fichiers créés** : 7 contrôleurs
**Fichiers proxyfiés** : ~44

---

## Phase 5 — Contrôleurs complexes (4 contrôleurs, ~49 endpoints)

**But** : Migrer les endpoints avec le plus de logique métier.

### 5.1 `MeetingsController` (25 endpoints)
Le plus gros : lifecycle, transitions, validation, reports, stats, summary, settings.
- `meetings.php`, `meetings_index.php`, `meetings_archive.php`, `meetings_update.php`
- `meeting_status.php`, `meeting_launch.php`, `meeting_validate.php`, `meeting_transition.php`
- `meeting_workflow_check.php`, `meeting_ready_check.php`, `meeting_consolidate.php`
- `meeting_stats.php`, `meeting_summary.php`, `meeting_report.php`, `meeting_generate_report_pdf.php`
- `meeting_quorum_settings.php`, `meeting_vote_settings.php`, `meeting_late_rules.php`
- `meeting_attachments.php`, `meeting_audit.php`, `meeting_audit_events.php`
- `meeting_reset_demo.php`, `meeting_ready_check.php`
- `emergency_check_toggle.php`, `emergency_procedures.php`
- Deps : MeetingRepository, MeetingWorkflowService, MeetingValidator, MeetingReportService
- Fichiers >200 lignes : `meeting_report.php` (360), `meeting_generate_report_pdf.php` (339), `meeting_transition.php` (169)

### 5.2 `AdminController` (11 endpoints)
- `admin_users.php`, `admin_roles.php`, `admin_meeting_roles.php`, `admin_audit_log.php`, `admin_system_status.php`
- `admin_quorum_policies.php`, `admin_vote_policies.php`
- `quorum_policies.php`, `vote_policies.php`
- `audit_log.php`, `audit_export.php`
- Deps : UserRepository, AuditEventRepository, PolicyRepository

### 5.3 `ExportController` (11 endpoints)
- `export_attendance_csv.php`, `export_attendance_xlsx.php`
- `export_votes_csv.php`, `export_votes_xlsx.php`
- `export_members_csv.php`
- `export_motions_results_csv.php`, `export_results_xlsx.php`
- `export_full_xlsx.php`
- `export_ballots_audit_csv.php`
- `export_pv_html.php`
- `export_templates.php` (GET/POST/PUT/DELETE)
- Deps : ExportService, ExportTemplateService, repositories divers

### 5.4 `OperatorController` (6 endpoints)
- `operator_anomalies.php`, `operator_audit_events.php`, `operator_open_vote.php`, `operator_workflow_state.php`
- `trust_anomalies.php`, `votes_export.php`
- Deps : MeetingRepository, MotionRepository, BallotRepository

**Fichiers créés** : 4 contrôleurs
**Fichiers proxyfiés** : ~53

---

## Phase 6 — Endpoints restants (2 contrôleurs, ~4 endpoints)

### 6.1 `AnalyticsController` (2 endpoints)
- `analytics.php` (352 lignes, 8 fonctions inline extraites en Phase 3.5), `reports_aggregate.php`
- Deps : AnalyticsRepository, AnalyticsService, ReportsService

### 6.2 `DevSeedController` (2 endpoints)
- `dev_seed_attendances.php`, `dev_seed_members.php`
- Protégé par `APP_ENV=development`
- Deps : MemberRepository, AttendanceRepository

**Fichiers créés** : 2 contrôleurs
**Fichiers proxyfiés** : 4

---

## Phase 7 — Nettoyage et finalisation

### 7.1 Supprimer les anciens fichiers proxy
- Après 4-6 semaines de fonctionnement avec proxys en production
- Supprimer les 142 anciens fichiers `.php`
- Simplifier nginx : tout passe par `index.php`

### 7.2 Convertir les 6 services statiques restants en instance
- `ImportService` (11 méthodes statiques) → instance + injection dans contrôleurs d'import
- `MeetingWorkflowService` (8 statiques) → instance
- `VoteTokenService` (5 statiques) → instance
- `ProxiesService` (4 statiques) → instance
- `AttendancesService` (5 statiques) → instance
- `NotificationsService` (8 statiques) → instance
- Total : 41 méthodes statiques à convertir

### 7.3 Conteneur de dépendances léger
- Créer `app/Container.php` (simple factory, pas un framework DI complet)
- Enregistrer tous les repos et services
- Les contrôleurs reçoivent le conteneur ou les dépendances nommées

### 7.4 Tests de couverture contrôleurs
- Tests unitaires pour chaque contrôleur (repos/services mockés)
- Objectif : 80%+ de couverture sur la couche contrôleur

---

## Résumé des phases

| Phase | Contrôleurs | Endpoints | Risque | Prérequis |
|---|---|---|---|---|
| 1. Infrastructure | 0 | 0 | Faible | Aucun |
| 2. Pilote Speech | 1 | 9 | Faible | Phase 1 |
| 3. Simples | 6 | 27 | Faible | Phase 1 |
| 3.5 Extraction services | 0 (+5 services) | 0 | Faible | Aucun |
| 4. Métier | 7 | ~44 | Moyen | Phase 1 + Phase 3.5 |
| 5. Complexes | 4 | ~53 | Moyen | Phase 3.5 |
| 6. Restants | 2 | ~4 | Faible | Phase 3.5 |
| 7. Nettoyage | 0 (+conteneur) | 0 | Faible | Toutes phases |
| **Total** | **~21** | **~137** | | |

Note : 5 endpoints spéciaux (`auth_login.php` pré-bootstrap, `vote.php` page HTML) restent en fichiers autonomes.

---

## Ce que ce plan ne fait PAS

- **Pas de réécriture du frontend** : les 82 URLs hardcodées continuent via proxys
- **Pas de changement de base de données** : les 28 repositories ne changent pas
- **Pas de framework externe** (Laravel, Symfony) : PHP vanilla + FastRoute uniquement
- **Pas de migration Big Bang** : chaque phase est indépendante et livrable
- **Pas de suppression immédiate** : proxys maintenus 4-6 semaines minimum

---

## Ordre d'exécution recommandé

1. Phase 1 (infrastructure) — immédiat
2. Phase 2 (pilote Speech) — valider le pattern
3. Phase 3 (simples) — gagner en confiance
4. Phase 3.5 (extraction 38 fonctions → 5 services) — prérequis bloquant
5. Phase 4 (métier) — le gros du travail
6. Phase 5 (complexes) — finaliser
7. Phase 6 (restants) — compléter la couverture
8. Phase 7 (nettoyage) — après validation en production
