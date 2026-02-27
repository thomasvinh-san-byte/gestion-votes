<?php

declare(strict_types=1);

/**
 * Route table for gestion-votes.
 *
 * All API v1 routes are registered here with middleware declarations.
 * The front controller loads this file to configure the router before dispatch.
 *
 * Middleware config keys:
 *   'role'       => string|array  — required role(s), handled by RoleMiddleware
 *   'rate_limit' => [ctx, max, window] — rate limiting, handled by RateLimitGuard
 *
 * Routes without middleware rely on the controller for auth (e.g., login, whoami).
 */

use AgVote\Controller\AdminController;
use AgVote\Controller\AgendaController;
use AgVote\Controller\AnalyticsController;
use AgVote\Controller\AttendancesController;
use AgVote\Controller\AuditController;
use AgVote\Controller\AuthController;
use AgVote\Controller\BallotsController;
use AgVote\Controller\DashboardController;
use AgVote\Controller\DevicesController;
use AgVote\Controller\DevSeedController;
use AgVote\Controller\DocController;
use AgVote\Controller\EmailController;
use AgVote\Controller\EmailTemplatesController;
use AgVote\Controller\EmailTrackingController;
use AgVote\Controller\EmergencyController;
use AgVote\Controller\ExportController;
use AgVote\Controller\ExportTemplatesController;
use AgVote\Controller\ImportController;
use AgVote\Controller\InvitationsController;
use AgVote\Controller\MeetingAttachmentController;
use AgVote\Controller\MeetingReportsController;
use AgVote\Controller\MeetingsController;
use AgVote\Controller\MeetingWorkflowController;
use AgVote\Controller\MemberGroupsController;
use AgVote\Controller\MembersController;
use AgVote\Controller\MotionsController;
use AgVote\Controller\NotificationsController;
use AgVote\Controller\OperatorController;
use AgVote\Controller\PoliciesController;
use AgVote\Controller\ProjectorController;
use AgVote\Controller\ProxiesController;
use AgVote\Controller\QuorumController;
use AgVote\Controller\ReminderController;
use AgVote\Controller\SpeechController;
use AgVote\Controller\TrustController;
use AgVote\Controller\VotePublicController;
use AgVote\Controller\VoteTokenController;
use AgVote\Core\Router;

return function (Router $router): void {

    $prefix = '/api/v1';

    // Shorthand middleware configs
    $op = ['role' => 'operator'];
    $admin = ['role' => 'admin'];
    $opAdm = ['role' => ['operator', 'admin']];
    $pub = ['role' => 'public'];
    $view = ['role' => 'viewer'];
    $audit = ['role' => 'auditor'];

    // ── Admin ──
    $rlAdmin = ['role' => 'admin', 'rate_limit' => ['admin_ops', 30, 60]];
    $rlOpAdm = ['role' => ['operator', 'admin'], 'rate_limit' => ['admin_ops', 30, 60]];
    $router->mapAny("{$prefix}/admin_audit_log", AdminController::class, 'auditLog', $admin);
    $router->mapAny("{$prefix}/admin_meeting_roles", AdminController::class, 'meetingRoles', $rlOpAdm);
    $router->mapAny("{$prefix}/admin_reset_demo", MeetingWorkflowController::class, 'resetDemo', $rlOpAdm);
    $router->mapAny("{$prefix}/admin_roles", AdminController::class, 'roles', $admin);
    $router->mapAny("{$prefix}/admin_system_status", AdminController::class, 'systemStatus', $rlAdmin);
    $router->mapAny("{$prefix}/admin_users", AdminController::class, 'users', $rlOpAdm);

    // ── Policies ──
    $router->mapAny("{$prefix}/admin_quorum_policies", PoliciesController::class, 'adminQuorum', $rlAdmin);
    $router->mapAny("{$prefix}/admin_vote_policies", PoliciesController::class, 'adminVote', $rlAdmin);
    $router->mapAny("{$prefix}/quorum_policies", PoliciesController::class, 'listQuorum', $op);
    $router->mapAny("{$prefix}/vote_policies", PoliciesController::class, 'listVote', $op);

    // ── Auth (controller handles its own auth) ──
    $router->mapAny("{$prefix}/auth_csrf", AuthController::class, 'csrf');
    $router->mapAny("{$prefix}/auth_login", AuthController::class, 'login', ['rate_limit' => ['auth_login', 10, 300]]);
    $router->mapAny("{$prefix}/auth_logout", AuthController::class, 'logout');
    $router->mapAny("{$prefix}/ping", AuthController::class, 'ping');
    $router->mapAny("{$prefix}/whoami", AuthController::class, 'whoami');

    // ── Agendas ──
    $router->mapMulti("{$prefix}/agendas", [
        'GET' => [AgendaController::class, 'listForMeeting', $op],
        'POST' => [AgendaController::class, 'create',         $op],
    ]);
    $router->map('GET', "{$prefix}/agendas_for_meeting", AgendaController::class, 'listForMeetingPublic', $pub);
    $router->mapAny("{$prefix}/meeting_late_rules", AgendaController::class, 'lateRules', $op);

    // ── Analytics ──
    $router->map('GET', "{$prefix}/analytics", AnalyticsController::class, 'analytics', $op);
    $router->map('GET', "{$prefix}/reports_aggregate", AnalyticsController::class, 'reportsAggregate', ['role' => ['operator', 'admin', 'auditor']]);

    // ── Attendances ──
    $router->mapAny("{$prefix}/attendances", AttendancesController::class, 'listForMeeting', ['role' => ['operator', 'trust', 'admin']]);
    $router->mapAny("{$prefix}/attendances_bulk", AttendancesController::class, 'bulk', $opAdm);
    $router->mapAny("{$prefix}/attendances_upsert", AttendancesController::class, 'upsert', $op);
    $router->mapAny("{$prefix}/attendance_present_from", AttendancesController::class, 'setPresentFrom', $op);
    $router->mapAny("{$prefix}/attendances_import_csv", ImportController::class, 'attendancesCsv', ['role' => ['operator', 'admin'], 'rate_limit' => ['csv_import', 10, 3600]]);
    $router->mapAny("{$prefix}/attendances_import_xlsx", ImportController::class, 'attendancesXlsx', ['role' => ['operator', 'admin'], 'rate_limit' => ['xlsx_import', 10, 3600]]);

    // ── Audit ──
    $router->mapAny("{$prefix}/audit_log", AuditController::class, 'timeline', ['role' => ['auditor', 'admin', 'operator', 'president']]);
    $router->mapAny("{$prefix}/audit_export", AuditController::class, 'export', ['role' => ['auditor', 'operator', 'admin']]);
    $router->mapAny("{$prefix}/meeting_audit", AuditController::class, 'meetingAudit', ['role' => ['auditor', 'admin']]);
    $router->mapAny("{$prefix}/meeting_audit_events", AuditController::class, 'meetingEvents', $audit);
    $router->mapAny("{$prefix}/operator_audit_events", AuditController::class, 'operatorEvents', ['role' => ['operator', 'admin', 'trust']]);

    // ── Ballots ──
    $router->map('GET', "{$prefix}/ballots", BallotsController::class, 'listForMotion', ['role' => ['operator', 'admin', 'president']]);
    $router->mapAny("{$prefix}/ballots_cancel", BallotsController::class, 'cancel', $opAdm);
    $router->mapAny("{$prefix}/ballots_cast", BallotsController::class, 'cast', ['role' => 'public', 'rate_limit' => ['ballot_cast', 60, 60]]);
    $router->map('GET', "{$prefix}/ballots_result", BallotsController::class, 'result', ['role' => 'public', 'rate_limit' => ['ballot_result', 120, 60]]);
    $router->mapAny("{$prefix}/manual_vote", BallotsController::class, 'manualVote', $op);
    $router->mapAny("{$prefix}/paper_ballot_redeem", BallotsController::class, 'redeemPaperBallot', $op);
    $router->mapAny("{$prefix}/vote_incident", BallotsController::class, 'reportIncident', ['role' => 'public', 'rate_limit' => ['vote_incident', 30, 60]]);

    // ── Dashboard ──
    $router->map('GET', "{$prefix}/dashboard", DashboardController::class, 'index', $op);
    $router->map('GET', "{$prefix}/wizard_status", DashboardController::class, 'wizardStatus', $view);

    // ── Devices ──
    $router->mapAny("{$prefix}/device_block", DevicesController::class, 'block', $opAdm);
    $router->mapAny("{$prefix}/device_heartbeat", DevicesController::class, 'heartbeat', ['role' => 'public', 'rate_limit' => ['device_heartbeat', 60, 60]]);
    $router->mapAny("{$prefix}/device_kick", DevicesController::class, 'kick', $opAdm);
    $router->mapAny("{$prefix}/device_unblock", DevicesController::class, 'unblock', $opAdm);
    $router->mapAny("{$prefix}/devices_list", DevicesController::class, 'listDevices', ['role' => ['operator', 'admin', 'trust']]);

    // ── Dev seed (dev only) ──
    $router->mapAny("{$prefix}/dev_seed_members", DevSeedController::class, 'seedMembers', $op);
    $router->mapAny("{$prefix}/dev_seed_attendances", DevSeedController::class, 'seedAttendances', $op);

    // ── Documentation ──
    $router->mapAny("{$prefix}/doc_index", DocController::class, 'index');
    // doc_content is a standalone file (no api.php, serves raw markdown)

    // ── Email ──
    $router->mapAny("{$prefix}/email_templates_preview", EmailController::class, 'preview', $opAdm);
    $router->mapAny("{$prefix}/invitations_schedule", EmailController::class, 'schedule', $op);
    $router->mapAny("{$prefix}/invitations_send_bulk", EmailController::class, 'sendBulk', $op);
    $router->mapMulti("{$prefix}/email_templates", [
        'GET' => [EmailTemplatesController::class, 'list',   $opAdm],
        'POST' => [EmailTemplatesController::class, 'create', $opAdm],
        'PUT' => [EmailTemplatesController::class, 'update', $opAdm],
        'DELETE' => [EmailTemplatesController::class, 'delete', $opAdm],
    ]);

    // ── Email tracking (bootstrap only, no api.php) ──
    $router->mapBootstrap("{$prefix}/email_pixel", EmailTrackingController::class, 'pixel');
    $router->mapBootstrap("{$prefix}/email_redirect", EmailTrackingController::class, 'redirect');

    // ── Emergency ──
    $router->mapAny("{$prefix}/emergency_check_toggle", EmergencyController::class, 'checkToggle', $opAdm);
    $router->mapAny("{$prefix}/emergency_procedures", EmergencyController::class, 'procedures', $op);

    // ── Exports ──
    $router->mapAny("{$prefix}/attendance_export", ExportController::class, 'attendanceCsv', $op);
    $router->mapAny("{$prefix}/export_attendance_csv", ExportController::class, 'attendanceCsv', $op);
    $router->mapAny("{$prefix}/export_attendance_xlsx", ExportController::class, 'attendanceXlsx', $op);
    $router->mapAny("{$prefix}/export_ballots_audit_csv", ExportController::class, 'ballotsAuditCsv', $op);
    $router->mapAny("{$prefix}/export_full_xlsx", ExportController::class, 'fullXlsx', ['role' => ['operator', 'admin', 'auditor']]);
    $router->mapAny("{$prefix}/export_members_csv", ExportController::class, 'membersCsv', $op);
    $router->mapAny("{$prefix}/export_motions_results_csv", ExportController::class, 'motionResultsCsv', ['role' => ['operator', 'admin', 'auditor']]);
    $router->mapAny("{$prefix}/export_pv_html", MeetingReportsController::class, 'exportPvHtml', $op);
    $router->mapAny("{$prefix}/export_results_xlsx", ExportController::class, 'resultsXlsx', ['role' => ['operator', 'admin', 'auditor']]);
    $router->mapAny("{$prefix}/export_votes_csv", ExportController::class, 'votesCsv', $op);
    $router->mapAny("{$prefix}/export_votes_xlsx", ExportController::class, 'votesXlsx', $op);
    $router->mapAny("{$prefix}/members_export", ExportController::class, 'membersCsv', $op);
    $router->mapAny("{$prefix}/members_export_csv", ExportController::class, 'membersCsv', $op);
    $router->mapAny("{$prefix}/motions_export", ExportController::class, 'motionResultsCsv', ['role' => ['operator', 'admin', 'auditor']]);
    $router->mapAny("{$prefix}/votes_export", ExportController::class, 'votesCsv', $op);
    $router->mapMulti("{$prefix}/export_templates", [
        'GET' => [ExportTemplatesController::class, 'list',   $opAdm],
        'POST' => [ExportTemplatesController::class, 'create', $opAdm],
        'PUT' => [ExportTemplatesController::class, 'update', $opAdm],
        'DELETE' => [ExportTemplatesController::class, 'delete', $opAdm],
    ]);

    // ── Import ──
    $rlCsv = ['role' => ['operator', 'admin'], 'rate_limit' => ['csv_import', 10, 3600]];
    $rlXlsx = ['role' => ['operator', 'admin'], 'rate_limit' => ['xlsx_import', 10, 3600]];
    $router->mapAny("{$prefix}/members_import_csv", ImportController::class, 'membersCsv', $rlCsv);
    $router->mapAny("{$prefix}/members_import_xlsx", ImportController::class, 'membersXlsx', $rlXlsx);
    $router->mapAny("{$prefix}/motions_import_csv", ImportController::class, 'motionsCsv', $rlCsv);
    $router->mapAny("{$prefix}/motions_import_xlsx", ImportController::class, 'motionsXlsx', $rlXlsx);

    // ── Invitations ──
    $router->mapAny("{$prefix}/invitations_create", InvitationsController::class, 'create', $op);
    $router->mapAny("{$prefix}/invitations_list", InvitationsController::class, 'listForMeeting', $op);
    $router->mapAny("{$prefix}/invitations_redeem", InvitationsController::class, 'redeem', ['role' => 'public', 'rate_limit' => ['invitation_redeem', 30, 60]]);
    $router->mapAny("{$prefix}/invitations_stats", InvitationsController::class, 'stats', ['role' => ['operator', 'admin', 'auditor']]);

    // ── Meetings ──
    $router->mapMulti("{$prefix}/meetings", [
        'GET' => [MeetingsController::class, 'index',         $view],
        'POST' => [MeetingsController::class, 'createMeeting', $op],
    ]);
    $router->map('GET', "{$prefix}/meetings_index", MeetingsController::class, 'index', $view);
    $router->mapAny("{$prefix}/meetings_update", MeetingsController::class, 'update', $op);
    $router->mapAny("{$prefix}/meetings_delete", MeetingsController::class, 'deleteMeeting', ['role' => 'admin']);
    $router->mapAny("{$prefix}/meetings_archive", MeetingsController::class, 'archive', $op);
    $router->map('GET', "{$prefix}/archives_list", MeetingsController::class, 'archivesList', $view);
    $router->mapAny("{$prefix}/meeting_status", MeetingsController::class, 'status', $op);
    $router->mapAny("{$prefix}/meeting_status_for_meeting", MeetingsController::class, 'statusForMeeting', $audit);
    $router->map('GET', "{$prefix}/meeting_summary", MeetingsController::class, 'summary', ['role' => ['operator', 'president', 'admin', 'auditor']]);
    $router->map('GET', "{$prefix}/meeting_stats", MeetingsController::class, 'stats', ['role' => 'public', 'rate_limit' => ['meeting_stats', 120, 60]]);
    $router->mapAny("{$prefix}/meeting_validate", MeetingsController::class, 'validate', ['role' => ['president', 'admin']]);
    $router->mapAny("{$prefix}/meeting_vote_settings", MeetingsController::class, 'voteSettings', $opAdm);

    // ── Meeting attachments ──
    $router->mapMulti("{$prefix}/meeting_attachments", [
        'GET' => [MeetingAttachmentController::class, 'listForMeeting', $op],
        'POST' => [MeetingAttachmentController::class, 'upload',         $op],
        'DELETE' => [MeetingAttachmentController::class, 'delete',         $op],
    ]);

    // ── Meeting workflow ──
    $trOpPresAdm = ['role' => ['operator', 'president', 'admin']];
    $router->mapAny("{$prefix}/meeting_consolidate", MeetingWorkflowController::class, 'consolidate', $opAdm);
    $router->mapAny("{$prefix}/meeting_launch", MeetingWorkflowController::class, 'launch', $trOpPresAdm);
    $router->mapAny("{$prefix}/meeting_ready_check", MeetingWorkflowController::class, 'readyCheck', $audit);
    $router->mapAny("{$prefix}/meeting_reset_demo", MeetingWorkflowController::class, 'resetDemo', $opAdm);
    $router->mapAny("{$prefix}/meeting_transition", MeetingWorkflowController::class, 'transition', $trOpPresAdm);
    $router->mapAny("{$prefix}/meeting_workflow_check", MeetingWorkflowController::class, 'workflowCheck', ['role' => ['operator', 'president', 'admin', 'viewer']]);

    // ── Meeting reports ──
    $router->mapAny("{$prefix}/meeting_generate_report", MeetingReportsController::class, 'generateReport', ['role' => 'president']);
    $router->mapAny("{$prefix}/meeting_generate_report_pdf", MeetingReportsController::class, 'generatePdf', ['role' => ['president', 'admin', 'operator', 'auditor']]);
    $router->mapAny("{$prefix}/meeting_report", MeetingReportsController::class, 'report', $audit);
    $router->mapAny("{$prefix}/meeting_report_send", MeetingReportsController::class, 'sendReport', $op);

    // ── Members ──
    $router->mapMulti("{$prefix}/members", [
        'GET' => [MembersController::class, 'index',        $op],
        'POST' => [MembersController::class, 'create',       $op],
        'PATCH' => [MembersController::class, 'updateMember', $op],
        'PUT' => [MembersController::class, 'updateMember', $op],
        'DELETE' => [MembersController::class, 'delete',       $op],
    ]);
    $router->map('GET', "{$prefix}/presidents", MembersController::class, 'presidents', $audit);

    // ── Member groups ──
    $router->mapMulti("{$prefix}/member_groups", [
        'GET' => [MemberGroupsController::class, 'list',   $opAdm],
        'POST' => [MemberGroupsController::class, 'create', $opAdm],
        'PATCH' => [MemberGroupsController::class, 'update', $opAdm],
        'DELETE' => [MemberGroupsController::class, 'delete', $opAdm],
    ]);
    $router->mapMulti("{$prefix}/member_group_assignments", [
        'POST' => [MemberGroupsController::class, 'assign',          $opAdm],
        'PUT' => [MemberGroupsController::class, 'setMemberGroups', $opAdm],
        'DELETE' => [MemberGroupsController::class, 'unassign',        $opAdm],
    ]);

    // ── Motions ──
    $router->mapAny("{$prefix}/motions", MotionsController::class, 'createOrUpdate', $op);
    $router->map('GET', "{$prefix}/motions_for_meeting", MotionsController::class, 'listForMeeting', ['role' => 'public', 'rate_limit' => ['motions_for_meeting', 120, 60]]);
    $router->mapAny("{$prefix}/motion_create_simple", MotionsController::class, 'createSimple', $op);
    $router->mapAny("{$prefix}/motion_delete", MotionsController::class, 'deleteMotion', $op);
    $router->mapAny("{$prefix}/motion_reorder", MotionsController::class, 'reorder', $op);
    $router->mapAny("{$prefix}/motion_tally", MotionsController::class, 'tally', $op);
    $router->map('GET', "{$prefix}/current_motion", MotionsController::class, 'current', ['role' => 'public', 'rate_limit' => ['current_motion', 120, 60]]);
    $router->mapAny("{$prefix}/motions_open", MotionsController::class, 'open', $op);
    $router->mapAny("{$prefix}/motions_close", MotionsController::class, 'close', ['role' => ['operator', 'president', 'admin']]);
    $router->mapAny("{$prefix}/degraded_tally", MotionsController::class, 'degradedTally', $op);

    // ── Notifications ──
    $router->map('GET', "{$prefix}/notifications", NotificationsController::class, 'list', $view);
    $router->map('PUT', "{$prefix}/notifications_read", NotificationsController::class, 'markRead', $view);

    // ── Operator ──
    $router->mapAny("{$prefix}/operator_anomalies", OperatorController::class, 'anomalies', $op);
    $router->mapAny("{$prefix}/operator_open_vote", OperatorController::class, 'openVote', $op);
    $router->mapAny("{$prefix}/operator_workflow_state", OperatorController::class, 'workflowState', $op);

    // ── Projector ──
    $router->map('GET', "{$prefix}/projector_state", ProjectorController::class, 'state', $pub);

    // ── Proxies ──
    $router->mapAny("{$prefix}/proxies", ProxiesController::class, 'listForMeeting', ['role' => ['operator', 'trust', 'admin']]);
    $router->mapAny("{$prefix}/proxies_delete", ProxiesController::class, 'delete', $opAdm);
    $router->mapAny("{$prefix}/proxies_upsert", ProxiesController::class, 'upsert', $op);
    $router->mapAny("{$prefix}/proxies_import_csv", ImportController::class, 'proxiesCsv', $rlCsv);
    $router->mapAny("{$prefix}/proxies_import_xlsx", ImportController::class, 'proxiesXlsx', $rlXlsx);

    // ── Quorum ──
    $router->map('GET', "{$prefix}/quorum_card", QuorumController::class, 'card', $pub);
    $router->mapAny("{$prefix}/quorum_status", QuorumController::class, 'status', $op);
    $router->mapAny("{$prefix}/meeting_quorum_settings", QuorumController::class, 'meetingSettings', $opAdm);

    // ── Reminders ──
    $router->mapMulti("{$prefix}/reminders", [
        'GET' => [ReminderController::class, 'listForMeeting', $opAdm],
        'POST' => [ReminderController::class, 'upsert',         $opAdm],
        'DELETE' => [ReminderController::class, 'delete',          $opAdm],
    ]);

    // ── Speech ──
    $router->mapAny("{$prefix}/speech_cancel", SpeechController::class, 'cancel', ['role' => ['operator', 'trust', 'president', 'admin']]);
    $router->mapAny("{$prefix}/speech_clear", SpeechController::class, 'clear', ['role' => ['operator', 'trust', 'president', 'admin']]);
    $router->map('GET', "{$prefix}/speech_current", SpeechController::class, 'current', ['role' => 'public', 'rate_limit' => ['speech_current', 120, 60]]);
    $router->mapAny("{$prefix}/speech_end", SpeechController::class, 'end', ['role' => ['operator', 'trust', 'president', 'admin']]);
    $router->mapAny("{$prefix}/speech_grant", SpeechController::class, 'grant', ['role' => ['operator', 'trust', 'president', 'admin']]);
    $router->map('GET', "{$prefix}/speech_my_status", SpeechController::class, 'myStatus', ['role' => 'public', 'rate_limit' => ['speech_my_status', 120, 60]]);
    $router->mapAny("{$prefix}/speech_next", SpeechController::class, 'next', ['role' => ['operator', 'president', 'admin']]);
    $router->map('GET', "{$prefix}/speech_queue", SpeechController::class, 'queue', ['role' => 'public', 'rate_limit' => ['speech_queue', 120, 60]]);
    $router->mapAny("{$prefix}/speech_request", SpeechController::class, 'request', ['role' => 'public', 'rate_limit' => ['speech_request', 30, 60]]);

    // ── Trust ──
    $router->mapAny("{$prefix}/trust_anomalies", TrustController::class, 'anomalies', ['role' => ['auditor', 'admin', 'operator']]);
    $router->mapAny("{$prefix}/trust_checks", TrustController::class, 'checks', ['role' => ['auditor', 'admin', 'operator']]);

    // ── Vote tokens ──
    $router->mapAny("{$prefix}/vote_tokens_generate", VoteTokenController::class, 'generate', $op);

    // ═════════════════════════════════════════════════════════════════════
    // PUBLIC HTML PAGES (no auth middleware — token/public access)
    // ═════════════════════════════════════════════════════════════════════

    // Vote form (token-authenticated, no role middleware)
    $router->mapAny('/vote', VotePublicController::class, 'vote');

    // Documentation viewer (public, no auth)
    $router->map('GET', '/doc', DocController::class, 'view');
};
