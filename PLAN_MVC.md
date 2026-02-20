# Plan de migration MVC — AG-VOTE

## Contexte

**État actuel** : 142 endpoints procéduraux, 28 repositories (propres), 18 services (mix static/instance), 0 routeur, routing fichier via nginx.

**Objectif** : Consolider 142 fichiers en ~15 contrôleurs MVC, simplifier les relations entre couches, améliorer la testabilité.

**Contrainte** : Migration incrémentale, rétrocompatibilité totale pendant la transition (les anciennes URLs continuent de fonctionner).

---

## Phase 1 — Micro-routeur + infrastructure contrôleur

**But** : Poser les fondations sans toucher aux endpoints existants.

### 1.1 Installer FastRoute (`nikic/fast-route`)
- Ajouter via composer
- Créer `app/Router.php` qui mappe `METHOD /api/v1/resource/action` → `Controller::method()`
- Créer un point d'entrée unique `public/api/v1/index.php` qui utilise le routeur
- Configurer nginx : les requêtes sans `.php` passent par `index.php`, les `.php` existants continuent de fonctionner

### 1.2 Créer `app/Controller/AbstractController.php`
```
- Accès à api_ok(), api_fail(), api_require_role(), api_current_tenant_id()
- Injection de dépendances via constructeur (repos + services)
- Méthode protégée input() → retourne les données parsées
- Méthode protégée guard() → vérifie meeting non validé, etc.
```

### 1.3 Tests d'infrastructure
- Test unitaire : le routeur résout les routes correctement
- Test unitaire : AbstractController expose les helpers

**Fichiers créés** : 3 (`Router.php`, `AbstractController.php`, `index.php`)
**Fichiers modifiés** : 1 (nginx.conf ou .htaccess)
**Risque** : Faible (aucun endpoint existant modifié)

---

## Phase 2 — Migration pilote : SpeechController (9 endpoints → 1 contrôleur)

**But** : Valider le pattern sur le groupe le plus simple (tous délèguent à SpeechService, aucune logique inline).

### 2.1 Créer `app/Controller/SpeechController.php`
Consolider 9 fichiers :
- `speech_request.php` → `POST /speeches/request`
- `speech_grant.php` → `POST /speeches/grant`
- `speech_end.php` → `POST /speeches/end`
- `speech_cancel.php` → `POST /speeches/cancel`
- `speech_clear.php` → `POST /speeches/clear`
- `speech_next.php` → `POST /speeches/next`
- `speech_queue.php` → `GET /speeches/queue`
- `speech_current.php` → `GET /speeches/current`
- `speech_my_status.php` → `GET /speeches/my-status`

### 2.2 Proxy les anciens fichiers
Chaque ancien `.php` devient un proxy de 3 lignes :
```php
require __DIR__ . '/../../../app/api.php';
(new \AgVote\Controller\SpeechController())->request();
```

### 2.3 Tests
- Test unitaire du contrôleur avec SpeechService mocké
- Vérifier que les anciens URLs fonctionnent toujours

**Fichiers créés** : 1 (SpeechController.php)
**Fichiers modifiés** : 9 (proxys)
**Lignes supprimées** : ~250 (remplacées par ~120 dans le contrôleur)

---

## Phase 3 — Contrôleurs à logique simple (4 contrôleurs, ~35 endpoints)

**But** : Migrer les groupes qui délèguent principalement aux repos/services.

### 3.1 `AuthController` (5 endpoints)
- `auth_login.php`, `auth_logout.php`, `auth_csrf.php`, `whoami.php`, `ping.php`
- Peu de logique, délèguent au middleware

### 3.2 `DeviceController` (6 endpoints)
- `devices_list.php`, `device_block.php`, `device_unblock.php`, `device_kick.php`, `device_heartbeat.php`, `projector_state.php`
- Délèguent à DeviceRepository

### 3.3 `InvitationsController` (6 endpoints)
- `invitations_create.php`, `invitations_list.php`, `invitations_redeem.php`, `invitations_schedule.php`, `invitations_send_bulk.php`, `invitations_stats.php`
- Délèguent à InvitationRepository

### 3.4 `AgendasController` (2 endpoints)
- `agendas.php`, `agendas_for_meeting.php`
- Délèguent à AgendaRepository

**Fichiers créés** : 4 contrôleurs
**Fichiers proxyfiés** : 19
**Lignes nettes** : ~ -300

---

## Phase 4 — Contrôleurs métier principaux (5 contrôleurs, ~60 endpoints)

**But** : Migrer le cœur de métier. C'est la phase la plus importante.

### 4.1 `MembersController` (~8 endpoints)
- CRUD `members.php` + imports CSV/XLSX + exports
- Dépendances : MemberRepository, MemberGroupRepository

### 4.2 `AttendancesController` (~9 endpoints)
- `attendances.php`, `attendances_bulk.php`, `attendances_upsert.php`, imports CSV/XLSX, `attendance_present_from.php`
- Dépendances : AttendanceRepository, AttendancesService

### 4.3 `ProxiesController` (~5 endpoints)
- `proxies.php`, `proxies_upsert.php`, `proxies_delete.php`, imports
- Dépendances : ProxyRepository, ProxiesService

### 4.4 `BallotsController` (~9 endpoints)
- `ballots.php`, `ballots_cast.php`, `ballots_cancel.php`, `ballots_result.php`, `manual_vote.php`, `vote_tokens_generate.php`, `degraded_tally.php`, `paper_ballot_redeem.php`, `vote_incident.php`
- Dépendances : BallotRepository, BallotsService, VoteTokenService

### 4.5 `MotionsController` (~11 endpoints)
- CRUD + open/close/reorder/tally + imports
- Dépendances : MotionRepository, OfficialResultsService, VoteEngine

**Fichiers créés** : 5 contrôleurs
**Fichiers proxyfiés** : ~42

### Extraction préalable nécessaire
Avant cette phase, extraire les fonctions procédurales des 11 fichiers qui en contiennent vers des services dédiés :
- `analytics.php` (8 fonctions) → `AnalyticsService`
- `meeting_report.php` (6 fonctions) → déjà un MeetingReportService, compléter
- `reports_aggregate.php` (2 fonctions) → `ReportsService`
- `member_groups.php` (4 fonctions) → `MemberGroupService`
- `member_group_assignments.php` (4 fonctions) → fusionner dans `MemberGroupService`
- `email_templates.php` (4 fonctions) → `EmailTemplateService` existe déjà, compléter
- `export_templates.php` (4 fonctions) → `ExportTemplateService`
- `reminders.php` (3 fonctions) → `ReminderService`

---

## Phase 5 — Contrôleurs complexes (3 contrôleurs, ~35 endpoints)

**But** : Migrer les endpoints avec le plus de logique métier.

### 5.1 `MeetingsController` (~19 endpoints)
Le plus gros : lifecycle, transitions, validation, reports, stats, summary
- Dépendances : MeetingRepository, MeetingWorkflowService, MeetingValidator, MeetingReportService
- Inclut `meeting_launch.php` (133 lignes), `meeting_transition.php` (169 lignes), `meeting_report.php` (360 lignes)

### 5.2 `AdminController` (~8 endpoints)
- Users, roles, policies, system status, audit log
- Dépendances : UserRepository, AuditEventRepository, PolicyRepository

### 5.3 `ExportController` (~11 endpoints)
- Tous les export_*.php (CSV, XLSX, HTML, PDF)
- Dépendances : ExportService, repositories divers
- Pattern: dispatch par type d'export

**Fichiers créés** : 3 contrôleurs
**Fichiers proxyfiés** : ~38

---

## Phase 6 — Nettoyage et finalisation

### 6.1 Supprimer les anciens fichiers proxy
- Une fois que le frontend utilise les nouvelles URLs (ou que les proxys ont été vérifiés en production pendant N semaines)
- Supprimer les 142 anciens fichiers `.php`
- Simplifier nginx : tout passe par `index.php`

### 6.2 Convertir les services statiques restants
Services encore statiques à convertir en instance :
- `ImportService` (11 méthodes statiques) → instance + injection dans contrôleurs d'import
- `MeetingWorkflowService` (8 statiques) → instance
- `VoteTokenService` (5 statiques) → instance
- `ProxiesService` (4 statiques) → instance
- `AttendancesService` (5 statiques) → instance
- `NotificationsService` (8 statiques) → instance

### 6.3 Conteneur de dépendances léger
- Créer `app/Container.php` (simple factory, pas un framework DI complet)
- Enregistrer tous les repos et services
- Les contrôleurs reçoivent le conteneur ou les dépendances nommées

### 6.4 Tests de couverture contrôleurs
- Tests unitaires pour chaque contrôleur (repos/services mockés)
- Objectif : 80%+ de couverture sur la couche contrôleur

---

## Résumé des phases

| Phase | Contrôleurs | Endpoints migrés | Risque | Prérequis |
|-------|-------------|-----------------|--------|-----------|
| 1. Infrastructure | 0 | 0 | Faible | Aucun |
| 2. Pilote Speech | 1 | 9 | Faible | Phase 1 |
| 3. Simples | 4 | 19 | Faible | Phase 1 |
| 4. Métier | 5 | ~42 | Moyen | Phase 1 + extraction fonctions |
| 5. Complexes | 3 | ~38 | Moyen | Phase 4 |
| 6. Nettoyage | 0 | 0 | Faible | Toutes phases |

**Total** : ~15 contrôleurs remplaçant 142 fichiers procéduraux.

---

## Ce que ce plan ne fait PAS

- **Pas de réécriture du frontend** : les URLs changent progressivement, les anciens chemins restent actifs via proxys
- **Pas de changement de base de données** : les repositories ne changent pas
- **Pas de framework externe** (Laravel, Symfony) : on reste en PHP vanilla avec juste FastRoute
- **Pas de migration Big Bang** : chaque phase est indépendante et livrable

---

## Ordre d'exécution recommandé

1. Phase 1 (infrastructure) — immédiat
2. Phase 2 (pilote Speech) — valider le pattern
3. Phase 3 (simples) — gagner en confiance
4. Extraction des fonctions procédurales (prérequis Phase 4)
5. Phase 4 (métier) — le gros du travail
6. Phase 5 (complexes) — finaliser
7. Phase 6 (nettoyage) — après validation en production
