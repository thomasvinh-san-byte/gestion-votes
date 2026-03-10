<?php

declare(strict_types=1);

namespace AgVote\Command;

use AgVote\Core\Application;
use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Service\MonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'monitor:check',
    description: 'Run system monitoring checks: collect metrics, evaluate thresholds, send alerts',
)]
final class MonitoringCheckCommand extends Command {
    protected function configure(): void {
        $this
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Also clean up old metrics (>30d) and alerts (>90d)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $config = Application::config();
        $repo = RepositoryFactory::getInstance();
        $service = new MonitoringService($config, $repo);

        $result = $service->check();

        if ($input->getOption('json')) {
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $m = $result['metrics'];
            $output->writeln('<info>System Monitoring Check</info>');
            $output->writeln('');

            // Metrics summary
            $output->writeln('  <comment>Metrics:</comment>');
            $output->writeln(sprintf('    DB latency:    %s', $m['db_latency_ms'] !== null ? $m['db_latency_ms'] . ' ms' : '<error>UNREACHABLE</error>'));
            $output->writeln(sprintf('    DB connections: %s', $m['db_active_connections'] ?? '?'));
            $output->writeln(sprintf('    Disk free:     %s', $m['disk_free_pct'] !== null ? $m['disk_free_pct'] . '%' : '?'));
            $output->writeln(sprintf('    Auth failures:  %s (15min)', $m['auth_failures_15m'] ?? '?'));
            $output->writeln(sprintf('    Email backlog:  %s', $m['email_queue_backlog']));
            $output->writeln(sprintf('    Meetings:       %s', $m['count_meetings'] ?? '?'));
            $output->writeln(sprintf('    Memory:         %s MB', $m['memory_usage_mb']));
            $output->writeln('');

            // Alerts
            $alerts = $result['alerts_created'];
            if (empty($alerts)) {
                $output->writeln('  <info>No new alerts.</info>');
            } else {
                $output->writeln(sprintf('  <error>%d new alert(s):</error>', count($alerts)));
                foreach ($alerts as $a) {
                    $tag = $a['severity'] === 'critical' ? 'error' : 'comment';
                    $output->writeln(sprintf('    <%s>[%s] %s: %s</%s>', $tag, strtoupper($a['severity']), $a['code'], $a['message'], $tag));
                }
            }

            // Notifications
            if ($result['notifications_sent'] > 0) {
                $output->writeln(sprintf('  <info>%d notification(s) sent.</info>', $result['notifications_sent']));
            }
        }

        // Cleanup
        if ($input->getOption('cleanup')) {
            $output->writeln('');
            $output->writeln('  <comment>Cleanup:</comment>');
            $metricsDeleted = $service->cleanupMetrics(30);
            $alertsDeleted = $service->cleanupAlerts(90);
            $output->writeln(sprintf('    Metrics deleted (>30d): %d', $metricsDeleted));
            $output->writeln(sprintf('    Alerts deleted (>90d):  %d', $alertsDeleted));
        }

        return empty($result['alerts_created']) ? Command::SUCCESS : Command::FAILURE;
    }
}
