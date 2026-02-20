<?php
declare(strict_types=1);

/**
 * Route table for gestion-votes.
 *
 * All API v1 routes are registered here. The front controller (public/index.php)
 * loads this file to configure the router before dispatch.
 *
 * Methods:
 *   mapAny()   — accepts any HTTP method, controller validates method internally
 *   mapMulti() — dispatches different HTTP methods to different controller methods
 *   mapBootstrap() — special routes that don't use api.php (e.g., email tracking)
 */

use AgVote\Core\Router;
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
use AgVote\Controller\EmergencyController;
use AgVote\Controller\EmailTrackingController;
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
use AgVote\Controller\OperatorController;
use AgVote\Controller\PoliciesController;
use AgVote\Controller\ProjectorController;
use AgVote\Controller\ProxiesController;
use AgVote\Controller\QuorumController;
use AgVote\Controller\ReminderController;
use AgVote\Controller\SpeechController;
use AgVote\Controller\TrustController;
use AgVote\Controller\VoteTokenController;

return function (Router $router): void {

    $prefix = '/api/v1';

    // ── Admin ──
    $router->mapAny("$prefix/admin_audit_log",     AdminController::class,    'auditLog');
    $router->mapAny("$prefix/admin_meeting_roles",  AdminController::class,    'meetingRoles');
    $router->mapAny("$prefix/admin_reset_demo",     MeetingWorkflowController::class, 'resetDemo');
    $router->mapAny("$prefix/admin_roles",          AdminController::class,    'roles');
    $router->mapAny("$prefix/admin_system_status",  AdminController::class,    'systemStatus');
    $router->mapAny("$prefix/admin_users",          AdminController::class,    'users');

    // ── Policies ──
    $router->mapAny("$prefix/admin_quorum_policies", PoliciesController::class, 'adminQuorum');
    $router->mapAny("$prefix/admin_vote_policies",   PoliciesController::class, 'adminVote');
    $router->mapAny("$prefix/quorum_policies",       PoliciesController::class, 'listQuorum');
    $router->mapAny("$prefix/vote_policies",         PoliciesController::class, 'listVote');

    // ── Auth ──
    $router->mapAny("$prefix/auth_csrf",   AuthController::class, 'csrf');
    $router->mapAny("$prefix/auth_login",  AuthController::class, 'login');
    $router->mapAny("$prefix/auth_logout", AuthController::class, 'logout');
    $router->mapAny("$prefix/ping",        AuthController::class, 'ping');
    $router->mapAny("$prefix/whoami",      AuthController::class, 'whoami');

    // ── Agendas ──
    $router->mapMulti("$prefix/agendas", [
        'GET'  => [AgendaController::class, 'listForMeeting'],
        'POST' => [AgendaController::class, 'create'],
    ]);
    $router->mapAny("$prefix/agendas_for_meeting", AgendaController::class, 'listForMeetingPublic');
    $router->mapAny("$prefix/meeting_late_rules",  AgendaController::class, 'lateRules');

    // ── Analytics ──
    $router->mapAny("$prefix/analytics",          AnalyticsController::class, 'analytics');
    $router->mapAny("$prefix/reports_aggregate",   AnalyticsController::class, 'reportsAggregate');

    // ── Attendances ──
    $router->mapAny("$prefix/attendances",           AttendancesController::class, 'listForMeeting');
    $router->mapAny("$prefix/attendances_bulk",      AttendancesController::class, 'bulk');
    $router->mapAny("$prefix/attendances_upsert",    AttendancesController::class, 'upsert');
    $router->mapAny("$prefix/attendance_present_from", AttendancesController::class, 'setPresentFrom');
    $router->mapAny("$prefix/attendances_import_csv",  ImportController::class, 'attendancesCsv');
    $router->mapAny("$prefix/attendances_import_xlsx", ImportController::class, 'attendancesXlsx');

    // ── Audit ──
    $router->mapAny("$prefix/audit_log",            AuditController::class, 'timeline');
    $router->mapAny("$prefix/audit_export",         AuditController::class, 'export');
    $router->mapAny("$prefix/meeting_audit",        AuditController::class, 'meetingAudit');
    $router->mapAny("$prefix/meeting_audit_events", AuditController::class, 'meetingEvents');
    $router->mapAny("$prefix/operator_audit_events", AuditController::class, 'operatorEvents');

    // ── Ballots ──
    $router->mapAny("$prefix/ballots",        BallotsController::class, 'listForMotion');
    $router->mapAny("$prefix/ballots_cancel",  BallotsController::class, 'cancel');
    $router->mapAny("$prefix/ballots_cast",    BallotsController::class, 'cast');
    $router->mapAny("$prefix/ballots_result",  BallotsController::class, 'result');
    $router->mapAny("$prefix/manual_vote",     BallotsController::class, 'manualVote');
    $router->mapAny("$prefix/paper_ballot_redeem", BallotsController::class, 'redeemPaperBallot');
    $router->mapAny("$prefix/vote_incident",   BallotsController::class, 'reportIncident');

    // ── Dashboard ──
    $router->mapAny("$prefix/dashboard",      DashboardController::class, 'index');
    $router->mapAny("$prefix/wizard_status",   DashboardController::class, 'wizardStatus');

    // ── Devices ──
    $router->mapAny("$prefix/device_block",     DevicesController::class, 'block');
    $router->mapAny("$prefix/device_heartbeat", DevicesController::class, 'heartbeat');
    $router->mapAny("$prefix/device_kick",      DevicesController::class, 'kick');
    $router->mapAny("$prefix/device_unblock",   DevicesController::class, 'unblock');
    $router->mapAny("$prefix/devices_list",     DevicesController::class, 'listDevices');

    // ── Dev seed (dev only) ──
    $router->mapAny("$prefix/dev_seed_members",     DevSeedController::class, 'seedMembers');
    $router->mapAny("$prefix/dev_seed_attendances",  DevSeedController::class, 'seedAttendances');

    // ── Documentation ──
    $router->mapAny("$prefix/doc_index", DocController::class, 'index');
    // doc_content is a standalone file (no api.php, serves raw markdown)

    // ── Emergency ──
    $router->mapAny("$prefix/emergency_check_toggle", EmergencyController::class, 'checkToggle');
    $router->mapAny("$prefix/emergency_procedures",   EmergencyController::class, 'procedures');

    // ── Email ──
    $router->mapAny("$prefix/email_templates_preview", EmailController::class, 'preview');
    $router->mapAny("$prefix/invitations_schedule",    EmailController::class, 'schedule');
    $router->mapAny("$prefix/invitations_send_bulk",   EmailController::class, 'sendBulk');
    $router->mapMulti("$prefix/email_templates", [
        'GET'    => [EmailTemplatesController::class, 'list'],
        'POST'   => [EmailTemplatesController::class, 'create'],
        'PUT'    => [EmailTemplatesController::class, 'update'],
        'DELETE' => [EmailTemplatesController::class, 'delete'],
    ]);

    // ── Email tracking (bootstrap only, no api.php) ──
    $router->mapBootstrap("$prefix/email_pixel",    EmailTrackingController::class, 'pixel');
    $router->mapBootstrap("$prefix/email_redirect", EmailTrackingController::class, 'redirect');

    // ── Exports ──
    $router->mapAny("$prefix/attendance_export",           ExportController::class, 'attendanceCsv');
    $router->mapAny("$prefix/export_attendance_csv",       ExportController::class, 'attendanceCsv');
    $router->mapAny("$prefix/export_attendance_xlsx",      ExportController::class, 'attendanceXlsx');
    $router->mapAny("$prefix/export_ballots_audit_csv",    ExportController::class, 'ballotsAuditCsv');
    $router->mapAny("$prefix/export_full_xlsx",            ExportController::class, 'fullXlsx');
    $router->mapAny("$prefix/export_members_csv",          ExportController::class, 'membersCsv');
    $router->mapAny("$prefix/export_motions_results_csv",  ExportController::class, 'motionResultsCsv');
    $router->mapAny("$prefix/export_pv_html",              MeetingReportsController::class, 'exportPvHtml');
    $router->mapAny("$prefix/export_results_xlsx",         ExportController::class, 'resultsXlsx');
    $router->mapAny("$prefix/export_votes_csv",            ExportController::class, 'votesCsv');
    $router->mapAny("$prefix/export_votes_xlsx",           ExportController::class, 'votesXlsx');
    $router->mapAny("$prefix/members_export",              ExportController::class, 'membersCsv');
    $router->mapAny("$prefix/members_export_csv",          ExportController::class, 'membersCsv');
    $router->mapAny("$prefix/motions_export",              ExportController::class, 'motionResultsCsv');
    $router->mapAny("$prefix/votes_export",                ExportController::class, 'votesCsv');
    $router->mapMulti("$prefix/export_templates", [
        'GET'    => [ExportTemplatesController::class, 'list'],
        'POST'   => [ExportTemplatesController::class, 'create'],
        'PUT'    => [ExportTemplatesController::class, 'update'],
        'DELETE' => [ExportTemplatesController::class, 'delete'],
    ]);

    // ── Import ──
    $router->mapAny("$prefix/members_import_csv",   ImportController::class, 'membersCsv');
    $router->mapAny("$prefix/members_import_xlsx",  ImportController::class, 'membersXlsx');
    $router->mapAny("$prefix/motions_import_csv",   ImportController::class, 'motionsCsv');
    $router->mapAny("$prefix/motions_import_xlsx",  ImportController::class, 'motionsXlsx');

    // ── Invitations ──
    $router->mapAny("$prefix/invitations_create", InvitationsController::class, 'create');
    $router->mapAny("$prefix/invitations_list",   InvitationsController::class, 'listForMeeting');
    $router->mapAny("$prefix/invitations_redeem", InvitationsController::class, 'redeem');
    $router->mapAny("$prefix/invitations_stats",  InvitationsController::class, 'stats');

    // ── Meetings ──
    $router->mapMulti("$prefix/meetings", [
        'GET'  => [MeetingsController::class, 'index'],
        'POST' => [MeetingsController::class, 'createMeeting'],
    ]);
    $router->mapAny("$prefix/meetings_index",   MeetingsController::class, 'index');
    $router->mapAny("$prefix/meetings_update",  MeetingsController::class, 'update');
    $router->mapAny("$prefix/meetings_archive", MeetingsController::class, 'archive');
    $router->mapAny("$prefix/archives_list",    MeetingsController::class, 'archivesList');
    $router->mapAny("$prefix/meeting_status",   MeetingsController::class, 'status');
    $router->mapAny("$prefix/meeting_status_for_meeting", MeetingsController::class, 'statusForMeeting');
    $router->mapAny("$prefix/meeting_summary",  MeetingsController::class, 'summary');
    $router->mapAny("$prefix/meeting_stats",    MeetingsController::class, 'stats');
    $router->mapAny("$prefix/meeting_validate",      MeetingsController::class, 'validate');
    $router->mapAny("$prefix/meeting_vote_settings", MeetingsController::class, 'voteSettings');

    // ── Meeting attachments ──
    $router->mapMulti("$prefix/meeting_attachments", [
        'GET'    => [MeetingAttachmentController::class, 'listForMeeting'],
        'POST'   => [MeetingAttachmentController::class, 'upload'],
        'DELETE' => [MeetingAttachmentController::class, 'delete'],
    ]);

    // ── Meeting workflow ──
    $router->mapAny("$prefix/meeting_consolidate",    MeetingWorkflowController::class, 'consolidate');
    $router->mapAny("$prefix/meeting_launch",         MeetingWorkflowController::class, 'launch');
    $router->mapAny("$prefix/meeting_ready_check",    MeetingWorkflowController::class, 'readyCheck');
    $router->mapAny("$prefix/meeting_reset_demo",     MeetingWorkflowController::class, 'resetDemo');
    $router->mapAny("$prefix/meeting_transition",     MeetingWorkflowController::class, 'transition');
    $router->mapAny("$prefix/meeting_workflow_check", MeetingWorkflowController::class, 'workflowCheck');

    // ── Meeting reports ──
    $router->mapAny("$prefix/meeting_generate_report",     MeetingReportsController::class, 'generateReport');
    $router->mapAny("$prefix/meeting_generate_report_pdf", MeetingReportsController::class, 'generatePdf');
    $router->mapAny("$prefix/meeting_report",              MeetingReportsController::class, 'report');
    $router->mapAny("$prefix/meeting_report_send",         MeetingReportsController::class, 'sendReport');

    // ── Members ──
    $router->mapMulti("$prefix/members", [
        'GET'    => [MembersController::class, 'index'],
        'POST'   => [MembersController::class, 'create'],
        'PATCH'  => [MembersController::class, 'updateMember'],
        'PUT'    => [MembersController::class, 'updateMember'],
        'DELETE' => [MembersController::class, 'delete'],
    ]);

    $router->mapAny("$prefix/presidents", MembersController::class, 'presidents');

    // ── Member groups ──
    $router->mapMulti("$prefix/member_groups", [
        'GET'    => [MemberGroupsController::class, 'list'],
        'POST'   => [MemberGroupsController::class, 'create'],
        'PATCH'  => [MemberGroupsController::class, 'update'],
        'DELETE' => [MemberGroupsController::class, 'delete'],
    ]);
    $router->mapMulti("$prefix/member_group_assignments", [
        'POST'   => [MemberGroupsController::class, 'assign'],
        'PUT'    => [MemberGroupsController::class, 'setMemberGroups'],
        'DELETE' => [MemberGroupsController::class, 'unassign'],
    ]);

    // ── Motions ──
    $router->mapAny("$prefix/motions",            MotionsController::class, 'createOrUpdate');
    $router->mapAny("$prefix/motions_for_meeting", MotionsController::class, 'listForMeeting');
    $router->mapAny("$prefix/motion_create_simple", MotionsController::class, 'createSimple');
    $router->mapAny("$prefix/motion_delete",       MotionsController::class, 'deleteMotion');
    $router->mapAny("$prefix/motion_reorder",      MotionsController::class, 'reorder');
    $router->mapAny("$prefix/motion_tally",        MotionsController::class, 'tally');
    $router->mapAny("$prefix/current_motion",      MotionsController::class, 'current');
    $router->mapAny("$prefix/motions_open",        MotionsController::class, 'open');
    $router->mapAny("$prefix/motions_close",       MotionsController::class, 'close');
    $router->mapAny("$prefix/degraded_tally",      MotionsController::class, 'degradedTally');

    // ── Operator ──
    $router->mapAny("$prefix/operator_anomalies",     OperatorController::class, 'anomalies');
    $router->mapAny("$prefix/operator_open_vote",     OperatorController::class, 'openVote');
    $router->mapAny("$prefix/operator_workflow_state", OperatorController::class, 'workflowState');

    // ── Projector ──
    $router->mapAny("$prefix/projector_state", ProjectorController::class, 'state');

    // ── Proxies ──
    $router->mapAny("$prefix/proxies",        ProxiesController::class, 'listForMeeting');
    $router->mapAny("$prefix/proxies_delete",  ProxiesController::class, 'delete');
    $router->mapAny("$prefix/proxies_upsert",  ProxiesController::class, 'upsert');
    $router->mapAny("$prefix/proxies_import_csv",  ImportController::class, 'proxiesCsv');
    $router->mapAny("$prefix/proxies_import_xlsx", ImportController::class, 'proxiesXlsx');

    // ── Quorum ──
    $router->mapAny("$prefix/quorum_card",             QuorumController::class, 'card');
    $router->mapAny("$prefix/quorum_status",           QuorumController::class, 'status');
    $router->mapAny("$prefix/meeting_quorum_settings", QuorumController::class, 'meetingSettings');

    // ── Reminders ──
    $router->mapMulti("$prefix/reminders", [
        'GET'    => [ReminderController::class, 'listForMeeting'],
        'POST'   => [ReminderController::class, 'upsert'],
        'DELETE' => [ReminderController::class, 'delete'],
    ]);

    // ── Speech ──
    $router->mapAny("$prefix/speech_cancel",    SpeechController::class, 'cancel');
    $router->mapAny("$prefix/speech_clear",     SpeechController::class, 'clear');
    $router->mapAny("$prefix/speech_current",   SpeechController::class, 'current');
    $router->mapAny("$prefix/speech_end",       SpeechController::class, 'end');
    $router->mapAny("$prefix/speech_grant",     SpeechController::class, 'grant');
    $router->mapAny("$prefix/speech_my_status", SpeechController::class, 'myStatus');
    $router->mapAny("$prefix/speech_next",      SpeechController::class, 'next');
    $router->mapAny("$prefix/speech_queue",     SpeechController::class, 'queue');
    $router->mapAny("$prefix/speech_request",   SpeechController::class, 'request');

    // ── Trust ──
    $router->mapAny("$prefix/trust_anomalies", TrustController::class, 'anomalies');
    $router->mapAny("$prefix/trust_checks",    TrustController::class, 'checks');

    // ── Vote tokens ──
    $router->mapAny("$prefix/vote_tokens_generate", VoteTokenController::class, 'generate');
};
