<?php

declare(strict_types=1);

namespace AgVote\Core\Providers;

use AgVote\Repository\AgendaRepository;
use AgVote\Repository\AggregateReportRepository;
use AgVote\Repository\AnalyticsRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\DeviceRepository;
use AgVote\Repository\EmailEventRepository;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\EmailTemplateRepository;
use AgVote\Repository\EmergencyProcedureRepository;
use AgVote\Repository\ExportTemplateRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingAttachmentRepository;
use AgVote\Repository\MeetingReportRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\NotificationRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\ReminderScheduleRepository;
use AgVote\Repository\SpeechRepository;
use AgVote\Repository\UserRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Repository\WizardRepository;
use PDO;

/**
 * Lazy-instantiation factory for all repositories.
 *
 * Caches instances so the same repository is reused within a request.
 * Accepts an optional PDO for testing; defaults to the global db().
 */
final class RepositoryFactory {
    private static ?self $instance = null;

    private ?PDO $pdo;

    /** @var array<class-string, object> */
    private array $cache = [];

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
    }

    /**
     * Global singleton for the current request.
     */
    public static function getInstance(): self {
        return self::$instance ??= new self();
    }

    /**
     * Reset singleton (for tests).
     */
    public static function reset(): void {
        self::$instance = null;
    }

    // =========================================================================
    // REPOSITORY ACCESSORS
    // =========================================================================

    public function agenda(): AgendaRepository { return $this->get(AgendaRepository::class); }
    public function aggregateReport(): AggregateReportRepository { return $this->get(AggregateReportRepository::class); }
    public function analytics(): AnalyticsRepository { return $this->get(AnalyticsRepository::class); }
    public function attendance(): AttendanceRepository { return $this->get(AttendanceRepository::class); }
    public function auditEvent(): AuditEventRepository { return $this->get(AuditEventRepository::class); }
    public function ballot(): BallotRepository { return $this->get(BallotRepository::class); }
    public function device(): DeviceRepository { return $this->get(DeviceRepository::class); }
    public function emailEvent(): EmailEventRepository { return $this->get(EmailEventRepository::class); }
    public function emailQueue(): EmailQueueRepository { return $this->get(EmailQueueRepository::class); }
    public function emailTemplate(): EmailTemplateRepository { return $this->get(EmailTemplateRepository::class); }
    public function emergencyProcedure(): EmergencyProcedureRepository { return $this->get(EmergencyProcedureRepository::class); }
    public function exportTemplate(): ExportTemplateRepository { return $this->get(ExportTemplateRepository::class); }
    public function invitation(): InvitationRepository { return $this->get(InvitationRepository::class); }
    public function manualAction(): ManualActionRepository { return $this->get(ManualActionRepository::class); }
    public function meetingAttachment(): MeetingAttachmentRepository { return $this->get(MeetingAttachmentRepository::class); }
    public function meetingReport(): MeetingReportRepository { return $this->get(MeetingReportRepository::class); }
    public function meeting(): MeetingRepository { return $this->get(MeetingRepository::class); }
    public function meetingStats(): MeetingStatsRepository { return $this->get(MeetingStatsRepository::class); }
    public function memberGroup(): MemberGroupRepository { return $this->get(MemberGroupRepository::class); }
    public function member(): MemberRepository { return $this->get(MemberRepository::class); }
    public function motion(): MotionRepository { return $this->get(MotionRepository::class); }
    public function notification(): NotificationRepository { return $this->get(NotificationRepository::class); }
    public function policy(): PolicyRepository { return $this->get(PolicyRepository::class); }
    public function proxy(): ProxyRepository { return $this->get(ProxyRepository::class); }
    public function reminderSchedule(): ReminderScheduleRepository { return $this->get(ReminderScheduleRepository::class); }
    public function speech(): SpeechRepository { return $this->get(SpeechRepository::class); }
    public function user(): UserRepository { return $this->get(UserRepository::class); }
    public function voteToken(): VoteTokenRepository { return $this->get(VoteTokenRepository::class); }
    public function wizard(): WizardRepository { return $this->get(WizardRepository::class); }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    private function get(string $class): object {
        return $this->cache[$class] ??= new $class($this->pdo);
    }
}
