# Plan de migration MVC — AG-VOTE (v3)

## Principes

1. **Ne consolider que si ça élimine de la duplication réelle.** Un contrôleur qui est un sac de méthodes sans rien en commun est pire que des fichiers séparés.
2. **Pas de router, pas de front controller, pas de DI container.** Nginx fait le routing. AbstractController (28 lignes) fait la gestion d'erreur. C'est suffisant.
3. **Extraire les services au moment où on consolide, pas avant.** Pas de phase "prérequis bloquant" artificielle.
4. **Les fichiers autonomes qui marchent restent autonomes.** Un fichier de 20 lignes avec une logique unique n'a pas besoin d'un contrôleur.

---

## État actuel

| Élément | Avant | Après |
|---|---|---|
| AbstractController | — | ✅ 28 lignes |
| SpeechController | 9 fichiers, ~290 LOC | ✅ 1 contrôleur, 170 LOC, -121 net |
| ExportController | 9 fichiers, ~570 LOC | ✅ 1 contrôleur, 195 LOC, -513 net |
| **Total consolidé** | **18 endpoints** | **-634 lignes** |

Reste : **124 fichiers** non consolidés.

---

## Groupes à consolider

### Batch A — Groupes simples (patterns évidents, peu de logique métier)

#### A1. DevicesController (5 fichiers, ~200 LOC)
`devices_list.php`, `device_block.php`, `device_unblock.php`, `device_kick.php`, `device_heartbeat.php`

**Pattern commun :** 4/5 partagent device_id + audit_log. block/unblock/kick sont quasi-identiques.
**Gain estimé :** ~-100 LOC

#### A2. PoliciesController (4 fichiers, ~350 LOC)
`quorum_policies.php`, `vote_policies.php`, `admin_quorum_policies.php`, `admin_vote_policies.php`

**Pattern commun :** Paires CRUD identiques (list public + admin CRUD) pour 2 types de policies.
**Gain estimé :** ~-150 LOC

#### A3. InvitationsController (4 fichiers, ~180 LOC)
`invitations_create.php`, `invitations_list.php`, `invitations_redeem.php`, `invitations_stats.php`

**Pattern commun :** Validation meeting_id, InvitationRepository.
**Gain estimé :** ~-80 LOC

**Sous-total Batch A : 13 fichiers → 3 contrôleurs, ~-330 LOC**

---

### Batch B — Domaine métier principal (cœur de l'application)

#### B1. AttendancesController (5 fichiers, ~260 LOC, hors imports)
`attendances.php`, `attendances_upsert.php`, `attendances_bulk.php`, `attendance_present_from.php`, `agendas_for_meeting.php`

**Pattern commun :** Validation meeting_id + meeting pas validé, AttendancesService.
**Note :** `agendas_for_meeting.php` est un GET simple, inclus car même domaine "données de séance".
**Gain estimé :** ~-120 LOC

#### B2. ProxiesController (3 fichiers, ~175 LOC)
`proxies.php`, `proxies_upsert.php`, `proxies_delete.php`

**Pattern commun :** Validation meeting_id + meeting pas validé, ProxiesService.
**Gain estimé :** ~-60 LOC

#### B3. BallotsController (7 fichiers, ~350 LOC)
`ballots.php`, `ballots_cast.php`, `ballots_cancel.php`, `ballots_result.php`, `manual_vote.php`, `paper_ballot_redeem.php`, `vote_incident.php`

**Pattern commun :** Validation motion/meeting, BallotRepository.
**Gain estimé :** ~-150 LOC

#### B4. MotionsController (7 fichiers, ~530 LOC)
`motions.php`, `motions_for_meeting.php`, `motion_create_simple.php`, `motion_delete.php`, `motion_reorder.php`, `motion_tally.php`, `current_motion.php`

**Pattern commun :** Validation meeting + motion, MotionRepository + MeetingRepository.
**Gain estimé :** ~-200 LOC

**Sous-total Batch B : 22 fichiers → 4 contrôleurs, ~-530 LOC**

---

### Batch C — Admin, audit, opérateur

#### C1. AdminController (5 fichiers, ~500 LOC)
`admin_users.php`, `admin_roles.php`, `admin_meeting_roles.php`, `admin_system_status.php`, `admin_audit_log.php`

**Pattern commun :** `api_require_role('admin')`, UserRepository.
**Gain estimé :** ~-120 LOC

#### C2. AuditController (5 fichiers, ~390 LOC)
`audit_log.php`, `audit_export.php`, `meeting_audit.php`, `meeting_audit_events.php`, `operator_audit_events.php`

**Pattern commun :** Requêtes d'audit paginées, même structure de réponse.
**Gain estimé :** ~-150 LOC

#### C3. OperatorController (3 fichiers, ~520 LOC)
`operator_workflow_state.php`, `operator_open_vote.php`, `operator_anomalies.php`

**Pattern commun :** `api_require_role('operator')` + état meeting complexe, MeetingRepository + MotionRepository + BallotRepository.
**Gain estimé :** ~-80 LOC

**Sous-total Batch C : 13 fichiers → 3 contrôleurs, ~-350 LOC**

---

### Batch D — Meetings (le gros morceau, 3 contrôleurs)

#### D1. MeetingsController (8 fichiers, ~500 LOC)
CRUD + status :
`meetings_index.php`, `meetings_update.php`, `meetings_archive.php`, `archives_list.php`, `meeting_status.php`, `meeting_status_for_meeting.php`, `meeting_summary.php`, `meeting_stats.php`

**Pattern commun :** MeetingRepository, tenant validation.

#### D2. MeetingWorkflowController (6 fichiers, ~580 LOC)
Transitions + checks :
`meeting_transition.php`, `meeting_launch.php`, `meeting_workflow_check.php`, `meeting_ready_check.php`, `meeting_consolidate.php`, `meeting_reset_demo.php`

**Pattern commun :** MeetingWorkflowService, validation pré-transition, transactions.
**Note :** `meeting_reset_demo.php` inclus car c'est un workflow (reset d'état).

#### D3. MeetingReportsController (5 fichiers, ~870 LOC)
Rapports :
`meeting_report.php` (361 LOC), `meeting_generate_report_pdf.php` (340 LOC), `meeting_generate_report.php` (79 LOC), `meeting_report_send.php` (52 LOC), `export_pv_html.php` (34 LOC)

**Pattern commun :** Validation meeting + meeting validé, MeetingReportService, génération HTML/PDF.
**Note :** `meeting_report.php` a 6 fonctions inline (`h()`, `decisionLabel()`, etc.) — extraire dans MeetingReportService au moment de la migration.

**Sous-total Batch D : 19 fichiers → 3 contrôleurs, ~-400 LOC**

---

### Batch E — Groupes restants à consolider

#### E1. EmailController (5 fichiers, ~345 LOC)
`email_pixel.php`, `email_redirect.php`, `email_templates_preview.php`, `invitations_schedule.php`, `invitations_send_bulk.php`

**Pattern commun :** Domaine email/notification, EmailEventRepository.

#### E2. ImportController (6 fichiers, ~1400 LOC estimé)
`members_import_csv.php`, `members_import_xlsx.php`, `attendances_import_csv.php`, `attendances_import_xlsx.php`, `proxies_import_csv.php`, `proxies_import_xlsx.php`

**Pattern commun :** Upload, parsing CSV/XLSX, validation, transaction, rapport d'erreurs.
**Note :** Tous suivent le même squelette (upload → parse → validate → insert → report). Pattern idéal pour un contrôleur avec un helper `processImport()`.

#### E3. TrustController (2 fichiers, ~420 LOC)
`trust_anomalies.php`, `trust_checks.php`

**Pattern commun :** `api_require_role(['auditor', 'admin', 'operator'])`, analyse multi-repo du meeting.

#### E4. AnalyticsController (2 fichiers, ~630 LOC)
`analytics.php` (353 LOC, 8 fonctions inline), `reports_aggregate.php` (275 LOC)

**Pattern commun :** Analyses multi-meetings, filtres date, formats multiples.
**Prérequis :** Extraire les 8+2 fonctions inline dans des services.

**Sous-total Batch E : 15 fichiers → 4 contrôleurs, ~-300 LOC**

---

## Fichiers qui restent autonomes

Ces fichiers n'ont pas de pattern commun avec d'autres fichiers. Créer un contrôleur pour eux ajouterait de la complexité sans éliminer de duplication.

| Fichier | LOC | Raison |
|---|---|---|
| `auth_login.php` | 151 | Logique session/rate-limit unique |
| `auth_csrf.php` | 22 | 5 lignes utiles |
| `whoami.php` | 56 | Logique unique (user+member+roles) |
| `ping.php` | 23 | Health check trivial |
| `doc_content.php` | 40 | Pas d'auth, lecture fichiers |
| `doc_index.php` | 51 | Pas d'auth, index statique |
| `dev_seed_attendances.php` | 65 | Dev only |
| `dev_seed_members.php` | 54 | Dev only |
| `projector_state.php` | 106 | Logique display unique |
| `quorum_card.php` | 68 | Retourne du HTML, unique |
| `quorum_status.php` | 39 | Petit fichier autonome |
| `wizard_status.php` | 80 | Endpoint polling unique |
| `dashboard.php` | 138 | Composite, pas de duplication |
| `presidents.php` | 23 | 5 lignes utiles |
| `emergency_check_toggle.php` | 39 | Petit fichier autonome |
| `emergency_procedures.php` | 21 | Petit fichier autonome |
| `vote_tokens_generate.php` | 87 | Logique unique (génération tokens) |
| `agendas.php` | ~50 | Petit CRUD autonome |
| `motions_open.php` | ~50 | Logique unique (ouverture vote) |
| `motions_close.php` | ~50 | Logique unique (fermeture vote) |
| `member_groups.php` | ~80 | CRUD avec fonctions inline |
| `member_group_assignments.php` | ~80 | CRUD avec fonctions inline |

**Total : ~22 fichiers autonomes (~1300 LOC)**

Ces fichiers bénéficient déjà de `api_request()`, `api_fail()`, `audit_log()`. La gestion d'erreur est cohérente. Le seul avantage d'un contrôleur serait le try/catch centralisé de AbstractController, ce qui ne justifie pas le coût.

---

## Alias à mettre à jour

6 fichiers qui font juste `require` vers un autre fichier. À convertir en proxies directs :

| Alias | Cible actuelle | Proxy vers |
|---|---|---|
| `admin_reset_demo.php` | `meeting_reset_demo.php` | `MeetingWorkflowController::resetDemo` |
| `attendance_export.php` | `export_attendance_csv.php` | `ExportController::attendanceCsv` |
| `members_export.php` | `export_members_csv.php` | `ExportController::membersCsv` |
| `members_export_csv.php` | `export_members_csv.php` | `ExportController::membersCsv` |
| `motions_export.php` | `export_motions_results_csv.php` | `ExportController::motionResultsCsv` |
| `votes_export.php` | `export_votes_csv.php` | `ExportController::votesCsv` |

---

## Résumé

| Batch | Contrôleurs | Endpoints | Gain estimé |
|---|---|---|---|
| ✅ Fait | 2 | 18 | -634 LOC |
| A. Simples | 3 | 13 | ~-330 LOC |
| B. Métier | 4 | 22 | ~-530 LOC |
| C. Admin/Audit | 3 | 13 | ~-350 LOC |
| D. Meetings | 3 | 19 | ~-400 LOC |
| E. Restants | 4 | 15 | ~-300 LOC |
| Alias | — | 6 | ~-30 LOC |
| **Total** | **19** | **106** | **~-2574 LOC** |
| Autonomes | — | 22 | Restent en l'état |

**19 contrôleurs pour 106 endpoints.** 22 fichiers restent autonomes. Pas de router, pas de container, pas de framework.

---

## Ordre d'exécution

L'ordre suit la logique : d'abord les groupes les plus simples (valider le pattern), puis les domaines métier, puis les gros morceaux.

1. **Batch A** — DevicesController, PoliciesController, InvitationsController
2. **Batch B** — AttendancesController, ProxiesController, BallotsController, MotionsController
3. **Batch C** — AdminController, AuditController, OperatorController
4. **Batch D** — MeetingsController, MeetingWorkflowController, MeetingReportsController
5. **Batch E** — EmailController, ImportController, TrustController, AnalyticsController
6. **Alias** — Mettre à jour les 6 alias
