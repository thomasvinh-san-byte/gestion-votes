<?php

declare(strict_types=1);

namespace AgVote\Command;

use AgVote\Core\Logger;
use AgVote\Core\Providers\DatabaseProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Prunes observability tables (error_events, next_step_clicks) older than the
 * configured retention window. These tables grow with every api_fail() and
 * every clicked next-step suggestion — without retention they would balloon
 * indefinitely.
 *
 * Default retention: 90 days for error_events (long enough for trend analysis
 * + post-incident review), 180 days for next_step_clicks (UX trend signal,
 * lower volume per row).
 *
 * Usage:
 *   php bin/console observability:purge-events
 *   php bin/console observability:purge-events --days=30 --dry-run
 *   php bin/console observability:purge-events --table=error_events --days=14
 */
#[AsCommand(
    name: 'observability:purge-events',
    description: 'Purge observability tables (error_events + next_step_clicks) past retention',
)]
final class ObservabilityPurgeCommand extends Command {
    private const DEFAULT_DAYS_ERROR_EVENTS = 90;
    private const DEFAULT_DAYS_NEXT_STEP = 180;

    protected function configure(): void {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Retention window in days (overrides per-table defaults)')
            ->addOption('table', 't', InputOption::VALUE_REQUIRED, 'Limit to one table: error_events | next_step_clicks (default: both)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show row counts that would be deleted without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $daysOverride = $input->getOption('days');
        $tableFilter = (string) ($input->getOption('table') ?? '');
        $dryRun = (bool) $input->getOption('dry-run');

        $tables = [
            'error_events' => self::DEFAULT_DAYS_ERROR_EVENTS,
            'next_step_clicks' => self::DEFAULT_DAYS_NEXT_STEP,
        ];

        if ($tableFilter !== '') {
            if (!isset($tables[$tableFilter])) {
                $output->writeln(sprintf('<error>Table inconnue: %s. Choix: error_events, next_step_clicks</error>', $tableFilter));
                return Command::FAILURE;
            }
            $tables = [$tableFilter => $tables[$tableFilter]];
        }

        $totalDeleted = 0;
        foreach ($tables as $table => $defaultDays) {
            $days = $daysOverride !== null ? max(1, (int) $daysOverride) : $defaultDays;
            $deleted = $this->purgeTable($table, $days, $dryRun, $output);
            $totalDeleted += $deleted;
        }

        $output->writeln(sprintf(
            '<info>%s : %d row(s) %s au total.</info>',
            $dryRun ? 'DRY-RUN' : 'Purge terminée',
            $totalDeleted,
            $dryRun ? 'éligibles' : 'supprimées',
        ));

        Logger::info('observability:purge-events completed', [
            'dry_run' => $dryRun,
            'total_affected' => $totalDeleted,
            'tables' => array_keys($tables),
        ]);

        return Command::SUCCESS;
    }

    private function purgeTable(string $table, int $days, bool $dryRun, OutputInterface $output): int {
        try {
            $pdo = DatabaseProvider::pdo();

            $countSql = sprintf(
                'SELECT COUNT(*) FROM %s WHERE occurred_at < NOW() - (:days || \' days\')::interval',
                $table,
            );
            $stmt = $pdo->prepare($countSql);
            $stmt->execute([':days' => (string) $days]);
            $eligible = (int) $stmt->fetchColumn();

            if ($eligible === 0) {
                $output->writeln(sprintf('  <comment>%s :</comment> rien à purger (>%d jours)', $table, $days));
                return 0;
            }

            $output->writeln(sprintf(
                '  <info>%s :</info> %d row(s) > %d jours',
                $table,
                $eligible,
                $days,
            ));

            if ($dryRun) {
                return $eligible;
            }

            $delSql = sprintf(
                'DELETE FROM %s WHERE occurred_at < NOW() - (:days || \' days\')::interval',
                $table,
            );
            $delStmt = $pdo->prepare($delSql);
            $delStmt->execute([':days' => (string) $days]);
            $rows = $delStmt->rowCount();

            $output->writeln(sprintf('    → %d supprimé(s).', $rows));
            return $rows;
        } catch (Throwable $e) {
            $output->writeln(sprintf('  <error>%s : échec — %s</error>', $table, $e->getMessage()));
            Logger::error('observability:purge-events table failed', [
                'table' => $table,
                'days' => $days,
                'exception' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}
