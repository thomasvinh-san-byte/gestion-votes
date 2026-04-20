# Audit Idempotence -- Inventaire des Routes Mutantes

**Date:** 2026-04-20
**Phase:** 01-audit-et-classification (Plan 01)
**Reference:** IDEM-01, IDEM-02

## Sommaire

| Metrique | Valeur |
|----------|--------|
| Routes mutantes totales | 73 |
| Protegees par IdempotencyGuard | 3 |
| Protegees par UNIQUE constraint | 8 |
| Protegees par Upsert (ON CONFLICT) | 11 |
| CSRF seulement | 36 |
| Rate limit + CSRF | 15 |
| Niveau Critique (sans protection idempotence) | 14 |
| Niveau Moyen | 12 |
| Niveau Bas | 47 |

## Legende

**Protection:**
- `IdempotencyGuard` -- utilise le guard Redis via X-Idempotency-Key header
- `UNIQUE constraint` -- la BD empeche les doublons via contrainte UNIQUE
- `CSRF only` -- uniquement protection CSRF, pas d'idempotence
- `Rate limit + CSRF` -- rate limiting + CSRF, pas d'idempotence
- `Upsert` -- operation inherement idempotente (INSERT ON CONFLICT UPDATE)
- `Business guard` -- logique metier empeche les doublons (ex: hasAnyAdmin)

**Niveau de risque:**
- **Critique** -- doublon a impact metier direct: double vote, double creation, double envoi email
- **Moyen** -- doublon cree du bruit: rappel en double, piece jointe en double
- **Bas** -- doublon inoffensif ou protege par BD: update idempotent, delete idempotent, contrainte UNIQUE

---

## Audit par Controleur

### AdminController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/admin_audit_log` | mapAny | AdminController::auditLog | CSRF only | Bas | GET-dominant, lecture du journal |
| `/api/v1/admin_meeting_roles` | mapAny | AdminController::meetingRoles | Rate limit + CSRF | Moyen | Peut assigner des roles en POST; upsert dans UserRepository |
| `/api/v1/admin_reset_demo` | mapAny | MeetingWorkflowController::resetDemo | Rate limit + CSRF | Moyen | Reset idempotent par nature mais effets de bord si double-clic |
| `/api/v1/admin_roles` | mapAny | AdminController::roles | CSRF only | Bas | Lecture/gestion roles systeme |
| `/api/v1/admin_system_status` | mapAny | AdminController::systemStatus | Rate limit + CSRF | Bas | Lecture statut systeme |
| `/api/v1/admin_users` | mapAny | AdminController::users | Rate limit + CSRF | Moyen | CRUD utilisateurs, creation peut dupliquer; UNIQUE(tenant_id,email) protege |

### PoliciesController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/admin_quorum_policies` | mapAny | PoliciesController::adminQuorum | Rate limit + CSRF | Critique | Creation politique quorum; UNIQUE(tenant_id,name) protege partiellement |
| `/api/v1/admin_vote_policies` | mapAny | PoliciesController::adminVote | Rate limit + CSRF | Critique | Creation politique vote; UNIQUE(tenant_id,name) protege partiellement |
| `/api/v1/quorum_policies` | mapAny | PoliciesController::listQuorum | CSRF only | Bas | Lecture politiques quorum |
| `/api/v1/vote_policies` | mapAny | PoliciesController::listVote | CSRF only | Bas | Lecture politiques vote |

### AuthController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/auth_csrf` | mapAny | AuthController::csrf | Rate limit + CSRF | Bas | Generation token CSRF, idempotent |
| `/api/v1/auth_login` | mapAny | AuthController::login | CSRF only | Bas | Login cree session, idempotent si deja connecte |
| `/api/v1/auth_logout` | mapAny | AuthController::logout | Rate limit + CSRF | Bas | Logout idempotent |
| `/api/v1/whoami` | mapAny | AuthController::whoami | Rate limit + CSRF | Bas | Lecture info session |

### AgendaController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/agendas` | POST | AgendaController::create | IdempotencyGuard | Bas | Protege par IdempotencyGuard |
| `/api/v1/meeting_late_rules` | mapAny | AgendaController::lateRules | CSRF only | Bas | Update regles de retard, idempotent |

### AttendancesController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/attendances` | mapAny | AttendancesController::listForMeeting | CSRF only | Bas | Lecture presences |
| `/api/v1/attendances_bulk` | mapAny | AttendancesController::bulk | CSRF only | Moyen | Bulk upsert presences; utilise ON CONFLICT |
| `/api/v1/attendances_upsert` | mapAny | AttendancesController::upsert | Upsert | Bas | ON CONFLICT(tenant_id,meeting_id,member_id) DO UPDATE |
| `/api/v1/attendance_present_from` | mapAny | AttendancesController::setPresentFrom | CSRF only | Bas | Update timestamp, idempotent |
| `/api/v1/attendances_import_csv` | mapAny | ImportController::attendancesCsv | Rate limit + CSRF | Critique | Import CSV peut creer des doublons si relance |
| `/api/v1/attendances_import_xlsx` | mapAny | ImportController::attendancesXlsx | Rate limit + CSRF | Critique | Import XLSX peut creer des doublons si relance |

### BallotsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/ballots_cancel` | mapAny | BallotsController::cancel | CSRF only | Bas | Annulation vote, idempotent |
| `/api/v1/ballots_cast` | mapAny | BallotsController::cast | UNIQUE constraint + Rate limit | Critique | UNIQUE(motion_id,member_id) protege; header X-Idempotency-Key en audit uniquement |
| `/api/v1/manual_vote` | mapAny | BallotsController::manualVote | CSRF only | Critique | Vote manuel operateur; UNIQUE(motion_id,member_id) protege |
| `/api/v1/paper_ballot_redeem` | mapAny | BallotsController::redeemPaperBallot | CSRF only | Critique | Echange bulletin papier; UNIQUE(code_hash) protege partiellement |
| `/api/v1/vote_incident` | mapAny | BallotsController::reportIncident | Rate limit + CSRF | Moyen | Signalement incident vote |

### DevicesController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/device_block` | mapAny | DevicesController::block | CSRF only | Bas | Blocage appareil, upsert ON CONFLICT |
| `/api/v1/device_heartbeat` | mapAny | DevicesController::heartbeat | Upsert + Rate limit | Bas | ON CONFLICT(device_id) DO UPDATE, idempotent |
| `/api/v1/device_kick` | mapAny | DevicesController::kick | CSRF only | Bas | Expulsion appareil, idempotent |
| `/api/v1/device_unblock` | mapAny | DevicesController::unblock | CSRF only | Bas | Deblocage appareil, idempotent |
| `/api/v1/devices_list` | mapAny | DevicesController::listDevices | CSRF only | Bas | Lecture liste appareils |

### EmailController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/email_templates_preview` | mapAny | EmailController::preview | CSRF only | Bas | Preview template, lecture |
| `/api/v1/invitations_schedule` | mapAny | EmailController::schedule | CSRF only | Critique | Planification envoi email; double-clic = double programmation |
| `/api/v1/invitations_send_bulk` | mapAny | EmailController::sendBulk | CSRF only | Critique | Envoi massif emails; double-clic = doublons dans queue |
| `/api/v1/invitations_send_reminder` | mapAny | EmailController::sendReminder | CSRF only | Critique | Envoi rappel; doublon = email en double |

### EmailTemplatesController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/email_templates` | POST | EmailTemplatesController::create | CSRF only | Critique | Creation template; UNIQUE(tenant_id,name) protege |
| `/api/v1/email_templates` | PUT | EmailTemplatesController::update | CSRF only | Bas | Update template, idempotent |
| `/api/v1/email_templates` | DELETE | EmailTemplatesController::delete | CSRF only | Bas | Delete template, idempotent |

### EmergencyController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/emergency_check_toggle` | mapAny | EmergencyController::checkToggle | CSRF only | Bas | Toggle etat urgence, idempotent |
| `/api/v1/emergency_procedures` | mapAny | EmergencyController::procedures | CSRF only | Bas | Lecture procedures urgence |

### ExportController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/attendance_export` | mapAny | ExportController::attendanceCsv | CSRF only | Bas | Export CSV, lecture seule |
| `/api/v1/export_attendance_csv` | mapAny | ExportController::attendanceCsv | CSRF only | Bas | Alias, export CSV |
| `/api/v1/export_attendance_xlsx` | mapAny | ExportController::attendanceXlsx | CSRF only | Bas | Export XLSX |
| `/api/v1/export_ballots_audit_csv` | mapAny | ExportController::ballotsAuditCsv | CSRF only | Bas | Export audit CSV |
| `/api/v1/export_full_xlsx` | mapAny | ExportController::fullXlsx | CSRF only | Bas | Export complet XLSX |
| `/api/v1/export_members_csv` | mapAny | ExportController::membersCsv | CSRF only | Bas | Export membres CSV |
| `/api/v1/export_motions_results_csv` | mapAny | ExportController::motionResultsCsv | CSRF only | Bas | Export resultats CSV |
| `/api/v1/export_pv_html` | mapAny | MeetingReportsController::exportPvHtml | CSRF only | Bas | Generation PV HTML, lecture |
| `/api/v1/export_results_xlsx` | mapAny | ExportController::resultsXlsx | CSRF only | Bas | Export resultats XLSX |
| `/api/v1/export_votes_csv` | mapAny | ExportController::votesCsv | CSRF only | Bas | Export votes CSV |
| `/api/v1/export_votes_xlsx` | mapAny | ExportController::votesXlsx | CSRF only | Bas | Export votes XLSX |
| `/api/v1/members_export` | mapAny | ExportController::membersCsv | CSRF only | Bas | Alias export membres |
| `/api/v1/members_export_csv` | mapAny | ExportController::membersCsv | CSRF only | Bas | Alias export membres |
| `/api/v1/motions_export` | mapAny | ExportController::motionResultsCsv | CSRF only | Bas | Alias export motions |
| `/api/v1/votes_export` | mapAny | ExportController::votesCsv | CSRF only | Bas | Alias export votes |

### ExportTemplatesController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/export_templates` | POST | ExportTemplatesController::create | CSRF only | Critique | Creation template export; UNIQUE(tenant_id,name,export_type) protege |
| `/api/v1/export_templates` | PUT | ExportTemplatesController::update | CSRF only | Bas | Update template, idempotent |
| `/api/v1/export_templates` | DELETE | ExportTemplatesController::delete | CSRF only | Bas | Delete template, idempotent |

### ImportController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/members_import_csv` | mapAny | ImportController::membersCsv | Rate limit + CSRF | Critique | Import membres; peut creer doublons; UNIQUE(tenant_id,full_name) protege partiellement |
| `/api/v1/members_import_xlsx` | mapAny | ImportController::membersXlsx | Rate limit + CSRF | Critique | Import membres XLSX; meme risque |
| `/api/v1/motions_import_csv` | mapAny | ImportController::motionsCsv | Rate limit + CSRF | Critique | Import motions; UNIQUE(meeting_id,slug) protege partiellement |
| `/api/v1/motions_import_xlsx` | mapAny | ImportController::motionsXlsx | Rate limit + CSRF | Critique | Import motions XLSX; meme risque |
| `/api/v1/proxies_import_csv` | mapAny | ImportController::proxiesCsv | Rate limit + CSRF | Moyen | Import procurations; upsert ON CONFLICT |
| `/api/v1/proxies_import_xlsx` | mapAny | ImportController::proxiesXlsx | Rate limit + CSRF | Moyen | Import procurations XLSX; upsert ON CONFLICT |

### InvitationsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/invitations_create` | mapAny | InvitationsController::create | Upsert | Bas | ON CONFLICT(tenant_id,meeting_id,member_id) DO UPDATE |
| `/api/v1/invitations_list` | mapAny | InvitationsController::listForMeeting | CSRF only | Bas | Lecture invitations |
| `/api/v1/invitations_redeem` | mapAny | InvitationsController::redeem | Rate limit + CSRF | Moyen | Echange invitation; logique metier limite les doublons |
| `/api/v1/invitations_stats` | mapAny | InvitationsController::stats | CSRF only | Bas | Lecture statistiques |

### MeetingsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/meetings` | POST | MeetingsController::createMeeting | IdempotencyGuard | Bas | Protege par IdempotencyGuard |
| `/api/v1/meetings_update` | mapAny | MeetingsController::update | CSRF only | Bas | Update reunion, idempotent |
| `/api/v1/meetings_delete` | mapAny | MeetingsController::deleteMeeting | CSRF only | Bas | Delete reunion, idempotent |
| `/api/v1/meetings_archive` | mapAny | MeetingsController::archive | CSRF only | Bas | Archivage reunion, idempotent |
| `/api/v1/meeting_status` | mapAny | MeetingsController::status | CSRF only | Bas | Lecture/update statut |
| `/api/v1/meeting_status_for_meeting` | mapAny | MeetingsController::statusForMeeting | CSRF only | Bas | Lecture statut pour audit |
| `/api/v1/meeting_validate` | mapAny | MeetingsController::validate | CSRF only | Moyen | Validation PV; double-clic pourrait re-valider |
| `/api/v1/meeting_vote_settings` | mapAny | MeetingsController::voteSettings | CSRF only | Bas | Update parametres vote, idempotent |

### MeetingAttachmentController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/meeting_attachments` | POST | MeetingAttachmentController::upload | CSRF only | Moyen | Upload piece jointe; doublon = fichier en double |
| `/api/v1/meeting_attachments` | DELETE | MeetingAttachmentController::delete | CSRF only | Bas | Delete piece jointe, idempotent |

### ResolutionDocumentController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/resolution_documents` | POST | ResolutionDocumentController::upload | CSRF only | Moyen | Upload document resolution; doublon = fichier en double |
| `/api/v1/resolution_documents` | DELETE | ResolutionDocumentController::delete | CSRF only | Bas | Delete document, idempotent |

### MeetingWorkflowController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/meeting_consolidate` | mapAny | MeetingWorkflowController::consolidate | CSRF only | Bas | Consolidation, idempotent |
| `/api/v1/meeting_launch` | mapAny | MeetingWorkflowController::launch | CSRF only | Moyen | Lancement reunion; double-clic gere par statut machine |
| `/api/v1/meeting_ready_check` | mapAny | MeetingWorkflowController::readyCheck | CSRF only | Bas | Verification prereqs, lecture |
| `/api/v1/meeting_reset_demo` | mapAny | MeetingWorkflowController::resetDemo | CSRF only | Moyen | Alias reset demo |
| `/api/v1/meeting_transition` | mapAny | MeetingWorkflowController::transition | CSRF only | Moyen | Transition workflow; protege par machine d'etats |
| `/api/v1/meeting_workflow_check` | mapAny | MeetingWorkflowController::workflowCheck | CSRF only | Bas | Verification workflow, lecture |

### MeetingReportsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/meeting_generate_report` | mapAny | MeetingReportsController::generateReport | Upsert | Bas | Upsert rapport via ON CONFLICT, idempotent |
| `/api/v1/meeting_generate_report_pdf` | mapAny | MeetingReportsController::generatePdf | CSRF only | Bas | Generation PDF, lecture/generation |
| `/api/v1/meeting_report` | mapAny | MeetingReportsController::report | CSRF only | Bas | Lecture rapport |
| `/api/v1/meeting_report_send` | mapAny | MeetingReportsController::sendReport | CSRF only | Critique | Envoi rapport par email; doublon = email en double |

### MembersController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/members` | POST | MembersController::create | IdempotencyGuard | Bas | Protege par IdempotencyGuard; UNIQUE(tenant_id,full_name) aussi |
| `/api/v1/members` | PATCH/PUT | MembersController::updateMember | CSRF only | Bas | Update membre, idempotent |
| `/api/v1/members` | DELETE | MembersController::delete | CSRF only | Bas | Delete membre, idempotent |
| `/api/v1/members_bulk` | POST | MembersController::bulk | Rate limit + CSRF | Critique | Creation en masse; pas d'IdempotencyGuard |

### MemberGroupsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/member_groups` | POST | MemberGroupsController::create | CSRF only | Moyen | Creation groupe; UNIQUE(tenant_id,name) protege |
| `/api/v1/member_groups` | PATCH | MemberGroupsController::update | CSRF only | Bas | Update groupe, idempotent |
| `/api/v1/member_groups` | DELETE | MemberGroupsController::delete | CSRF only | Bas | Delete groupe, idempotent |
| `/api/v1/member_group_assignments` | POST | MemberGroupsController::assign | CSRF only | Moyen | Assignation membre-groupe; UNIQUE(member_id,group_id) protege |
| `/api/v1/member_group_assignments` | PUT | MemberGroupsController::setMemberGroups | CSRF only | Bas | Remplacement groupes, idempotent |
| `/api/v1/member_group_assignments` | DELETE | MemberGroupsController::unassign | CSRF only | Bas | Desassignation, idempotent |

### MotionsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/motions` | mapAny | MotionsController::createOrUpdate | CSRF only | Critique | Creation/update motion; pas d'IdempotencyGuard; UNIQUE(meeting_id,slug) partiel |
| `/api/v1/motion_create_simple` | mapAny | MotionsController::createSimple | CSRF only | Critique | Creation motion simplifiee; pas d'IdempotencyGuard |
| `/api/v1/motion_delete` | mapAny | MotionsController::deleteMotion | CSRF only | Bas | Delete motion, idempotent |
| `/api/v1/motion_reorder` | mapAny | MotionsController::reorder | CSRF only | Bas | Reordonnancement, idempotent |
| `/api/v1/motion_tally` | mapAny | MotionsController::tally | CSRF only | Bas | Depouillement, idempotent si meme donnees |
| `/api/v1/motions_open` | mapAny | MotionsController::open | CSRF only | Moyen | Ouverture vote; protege par machine d'etats |
| `/api/v1/motions_close` | mapAny | MotionsController::close | CSRF only | Moyen | Fermeture vote; protege par machine d'etats |
| `/api/v1/degraded_tally` | mapAny | MotionsController::degradedTally | CSRF only | Bas | Depouillement degrade, idempotent |
| `/api/v1/motions_override_decision` | mapAny | MotionsController::overrideDecision | CSRF only | Moyen | Surcharge decision; idempotent mais sensible |

### NotificationsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/notifications_read` | PUT | NotificationsController::markRead | CSRF only | Bas | Marquage lu, idempotent |

### OperatorController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/operator_anomalies` | mapAny | OperatorController::anomalies | CSRF only | Bas | Lecture anomalies |
| `/api/v1/operator_open_vote` | mapAny | OperatorController::openVote | CSRF only | Moyen | Ouverture vote operateur; protege par machine d'etats |
| `/api/v1/operator_workflow_state` | mapAny | OperatorController::workflowState | CSRF only | Bas | Lecture etat workflow |

### ProxiesController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/proxies` | mapAny | ProxiesController::listForMeeting | CSRF only | Bas | Lecture procurations |
| `/api/v1/proxies_delete` | mapAny | ProxiesController::delete | CSRF only | Bas | Delete procuration, idempotent |
| `/api/v1/proxies_upsert` | mapAny | ProxiesController::upsert | Upsert | Bas | ON CONFLICT(tenant_id,meeting_id,giver_member_id) DO UPDATE |

### QuorumController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/quorum_status` | mapAny | QuorumController::status | CSRF only | Bas | Lecture statut quorum |
| `/api/v1/meeting_quorum_settings` | mapAny | QuorumController::meetingSettings | CSRF only | Bas | Update parametres quorum, idempotent |

### ReminderController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/reminders` | POST | ReminderController::upsert | Upsert | Bas | ON CONFLICT(tenant_id,meeting_id,days_before) DO UPDATE |
| `/api/v1/reminders` | DELETE | ReminderController::delete | CSRF only | Bas | Delete rappel, idempotent |

### SettingsController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/admin_settings` | mapAny | SettingsController::settings | CSRF only | Bas | Update parametres, idempotent |

### SpeechController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/speech_cancel` | mapAny | SpeechController::cancel | CSRF only | Bas | Annulation prise de parole, idempotent |
| `/api/v1/speech_clear` | mapAny | SpeechController::clear | CSRF only | Bas | Nettoyage file, idempotent |
| `/api/v1/speech_end` | mapAny | SpeechController::end | CSRF only | Bas | Fin prise de parole, idempotent |
| `/api/v1/speech_grant` | mapAny | SpeechController::grant | CSRF only | Bas | Attribution parole, idempotent |
| `/api/v1/speech_next` | mapAny | SpeechController::next | CSRF only | Bas | Suivant dans file, idempotent |
| `/api/v1/speech_request` | mapAny | SpeechController::request | Rate limit + CSRF | Moyen | Demande parole; doublon = double entree file |

### TrustController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/trust_anomalies` | mapAny | TrustController::anomalies | CSRF only | Bas | Lecture anomalies |
| `/api/v1/trust_checks` | mapAny | TrustController::checks | CSRF only | Bas | Lecture verifications |

### VoteTokenController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/vote_tokens_generate` | mapAny | VoteTokenController::generate | Upsert | Bas | ON CONFLICT(token_hash) DO NOTHING |

### AuditController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/audit_log` | mapAny | AuditController::timeline | CSRF only | Bas | Lecture journal audit |
| `/api/v1/audit_export` | mapAny | AuditController::export | CSRF only | Bas | Export audit, lecture |
| `/api/v1/meeting_audit` | mapAny | AuditController::meetingAudit | CSRF only | Bas | Lecture audit reunion |
| `/api/v1/meeting_audit_events` | mapAny | AuditController::meetingEvents | CSRF only | Bas | Lecture evenements audit |
| `/api/v1/operator_audit_events` | mapAny | AuditController::operatorEvents | CSRF only | Bas | Lecture evenements operateur |

### ProcurationPdfController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/procuration_pdf` | mapAny | ProcurationPdfController::download | CSRF only | Bas | Generation PDF, lecture |

### RgpdExportController

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/rgpd_export` | GET | RgpdExportController::download | Rate limit + CSRF | Bas | Export RGPD, lecture |

### Pages HTML (routes publiques)

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/reset-password` | mapAny | PasswordResetController::resetPassword | CSRF only | Moyen | Reset mot de passe; doublon genere nouveau token mais inoffensif |
| `/setup` | mapAny | SetupController::setup | Business guard | Bas | hasAnyAdmin() empeche re-execution |
| `/account` | mapAny | AccountController::account | CSRF only | Bas | Update compte, idempotent |
| `/vote` | POST | VotePublicController::vote | CSRF only | Critique | Soumission vote public; UNIQUE(motion_id,member_id) protege |

### DevSeedController (dev/test uniquement)

| Route | Method | Controller::method | Protection | Risque | Notes |
|-------|--------|--------------------|------------|--------|-------|
| `/api/v1/dev_seed_members` | mapAny | DevSeedController::seedMembers | CSRF only | Bas | Dev only, pas en production |
| `/api/v1/dev_seed_attendances` | mapAny | DevSeedController::seedAttendances | CSRF only | Bas | Dev only, pas en production |
| `/api/v1/test/seed-user` | POST | DevSeedController::seedUser | Aucune | Bas | Dev only, pas en production |

---

## Cibles Phase 2

Routes de niveau **Critique** qui n'ont **ni IdempotencyGuard ni UNIQUE constraint suffisante** :

| # | Route | Controller::method | Protection actuelle | Risque | Justification |
|---|-------|--------------------|--------------------|--------|---------------|
| 1 | `/api/v1/invitations_schedule` | EmailController::schedule | CSRF only | Critique | Double-clic = double programmation d'envoi emails |
| 2 | `/api/v1/invitations_send_bulk` | EmailController::sendBulk | CSRF only | Critique | Double-clic = emails en double dans la queue |
| 3 | `/api/v1/invitations_send_reminder` | EmailController::sendReminder | CSRF only | Critique | Double-clic = rappels en double |
| 4 | `/api/v1/meeting_report_send` | MeetingReportsController::sendReport | CSRF only | Critique | Double-clic = envoi rapport en double |
| 5 | `/api/v1/members_bulk` | MembersController::bulk | Rate limit + CSRF | Critique | Creation en masse sans IdempotencyGuard |
| 6 | `/api/v1/motions` | MotionsController::createOrUpdate | CSRF only | Critique | Creation motion sans IdempotencyGuard; UNIQUE(slug) partiel |
| 7 | `/api/v1/motion_create_simple` | MotionsController::createSimple | CSRF only | Critique | Creation motion simplifiee sans IdempotencyGuard |
| 8 | `/api/v1/attendances_import_csv` | ImportController::attendancesCsv | Rate limit + CSRF | Critique | Import CSV peut dupliquer presences |
| 9 | `/api/v1/attendances_import_xlsx` | ImportController::attendancesXlsx | Rate limit + CSRF | Critique | Import XLSX peut dupliquer presences |
| 10 | `/api/v1/members_import_csv` | ImportController::membersCsv | Rate limit + CSRF | Critique | Import peut creer membres en double |
| 11 | `/api/v1/members_import_xlsx` | ImportController::membersXlsx | Rate limit + CSRF | Critique | Import peut creer membres en double |
| 12 | `/api/v1/motions_import_csv` | ImportController::motionsCsv | Rate limit + CSRF | Critique | Import peut creer motions en double |
| 13 | `/api/v1/motions_import_xlsx` | ImportController::motionsXlsx | Rate limit + CSRF | Critique | Import peut creer motions en double |

**Note:** Les routes suivantes sont classees Critique mais ont une protection UNIQUE suffisante et ne necessitent pas forcement IdempotencyGuard en Phase 2 :
- `ballots_cast` -- UNIQUE(motion_id, member_id) empeche le double vote
- `manual_vote` -- UNIQUE(motion_id, member_id) empeche le double vote
- `paper_ballot_redeem` -- UNIQUE(code_hash) empeche le double echange
- `/vote` (POST) -- UNIQUE(motion_id, member_id) empeche le double vote
- `admin_quorum_policies` / `admin_vote_policies` -- UNIQUE(tenant_id, name) empeche le doublon
- `email_templates` POST / `export_templates` POST -- UNIQUE sur nom empeche le doublon

## Contraintes UNIQUE en Base

| Table | Contrainte | Colonnes |
|-------|-----------|----------|
| tenants | slug UNIQUE | slug |
| users | ux_users_tenant_email | tenant_id, email |
| users | ux_users_tenant_api_key_hash | tenant_id, api_key_hash |
| members | ux_members_tenant_full_name | tenant_id, full_name |
| members | ux_members_tenant_external_ref | tenant_id, external_ref |
| member_groups | member_groups_unique_name | tenant_id, name |
| member_group_assignments | member_group_assignments_unique | member_id, group_id |
| quorum_policies | quorum_policies_unique_name | tenant_id, name |
| vote_policies | vote_policies_unique_name | tenant_id, name |
| meetings | ux_meetings_tenant_slug | tenant_id, slug |
| agenda_items | UNIQUE | tenant_id, meeting_id, idx |
| motions | ux_motions_meeting_slug | meeting_id, slug |
| email_templates | email_templates_unique_name | tenant_id, name |
| vote_tokens | token UNIQUE | token |
| invitations | UNIQUE | tenant_id, meeting_id, member_id |
| proxies | UNIQUE | tenant_id, meeting_id, giver_member_id |
| attendances | UNIQUE | tenant_id, meeting_id, member_id |
| ballots | UNIQUE | motion_id, member_id |
| export_templates | export_templates_unique_name | tenant_id, name, export_type |
| reminder_schedules | reminder_schedules_unique | tenant_id, meeting_id, days_before |
| device_blocks | ux_device_blocks_scope_device | scope + device_id |
| paper_ballots | ux_paper_ballots_code_hash | code_hash |
| meeting_roles | meeting_roles_unique_active | tenant_id, meeting_id, user_id, role |
