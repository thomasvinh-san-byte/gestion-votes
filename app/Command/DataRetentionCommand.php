<?php

declare(strict_types=1);

namespace AgVote\Command;

// CLI tool — intentionally retained, no unit test required for command bootstrap

use AgVote\Core\Application;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\SettingsRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'rgpd:purge-retention',
    description: 'Purge member data older than the configured retention period (RGPD)',
)]
final class DataRetentionCommand extends Command {
    protected function configure(): void {
        $this
            ->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant UUID to purge (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $tenantId = (string) $input->getOption('tenant-id');
        if ($tenantId === '') {
            $output->writeln('<error>--tenant-id est requis</error>');
            return Command::FAILURE;
        }

        $settingsRepo = new SettingsRepository();
        $memberRepo   = new MemberRepository();
        $dryRun       = (bool) $input->getOption('dry-run');

        $retentionStr = $settingsRepo->get($tenantId, 'data_retention_months');
        $months       = (int) ($retentionStr ?? 0);

        if ($months <= 0) {
            $output->writeln('<info>Retention non configuree pour ce tenant — aucune action.</info>');
            return Command::SUCCESS;
        }

        $expired = $memberRepo->findExpiredForTenant($tenantId, $months);
        $output->writeln(sprintf(
            '<info>%d membre(s) eligibles a la purge (> %d mois sans activite)</info>',
            count($expired),
            $months,
        ));

        if ($dryRun) {
            foreach ($expired as $m) {
                $output->writeln('  [DRY-RUN] ' . $m['full_name'] . ' (' . $m['id'] . ')');
            }
            return Command::SUCCESS;
        }

        $deleted = 0;
        foreach ($expired as $m) {
            $rows = $memberRepo->hardDeleteById($m['id'], $tenantId);
            if ($rows > 0) {
                $deleted++;
                (new \AgVote\Repository\AuditEventRepository())->anonymizeForResource('member', (string) $m['id']);
                $output->writeln('  Supprime : ' . $m['full_name'] . ' (' . $m['id'] . ')');
            }
        }
        $output->writeln(sprintf('<info>Purge terminee : %d membre(s) supprimes.</info>', $deleted));

        return Command::SUCCESS;
    }
}
