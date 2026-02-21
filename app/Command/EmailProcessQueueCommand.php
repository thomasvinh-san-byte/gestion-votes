<?php
declare(strict_types=1);

namespace AgVote\Command;

use AgVote\Core\Application;
use AgVote\Service\EmailQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'email:process-queue',
    description: 'Process pending emails from the queue',
)]
final class EmailProcessQueueCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of emails to process per batch', '50')
            ->addOption('reminders', 'r', InputOption::VALUE_NONE, 'Also process scheduled reminders');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = (int) $input->getOption('batch-size');
        $processReminders = (bool) $input->getOption('reminders');

        $config = Application::config();
        $service = new EmailQueueService($config);

        $output->writeln('<info>Processing email queue...</info>');

        $result = $service->processQueue($batchSize);

        $output->writeln(sprintf(
            '  Processed: %d | Sent: %d | Failed: %d',
            $result['processed'],
            $result['sent'],
            $result['failed']
        ));

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $output->writeln(sprintf(
                    '  <error>  Error: %s -> %s</error>',
                    $err['email'] ?? $err['queue_id'] ?? '?',
                    $err['error'] ?? 'unknown'
                ));
            }
        }

        if ($processReminders) {
            $output->writeln('<info>Processing reminders...</info>');
            $remResult = $service->processReminders();
            $output->writeln(sprintf(
                '  Processed: %d | Sent: %d',
                $remResult['processed'],
                $remResult['sent']
            ));
        }

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
