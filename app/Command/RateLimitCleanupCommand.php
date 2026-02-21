<?php

declare(strict_types=1);

namespace AgVote\Command;

use AgVote\Core\Security\RateLimiter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ratelimit:cleanup',
    description: 'Remove expired rate-limit tracking files',
)]
final class RateLimitCleanupCommand extends Command {
    protected function configure(): void {
        $this->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Max age in seconds', '3600');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $maxAge = (int) $input->getOption('max-age');

        $cleaned = RateLimiter::cleanup($maxAge);

        $output->writeln(sprintf('<info>Cleaned %d expired rate-limit file(s).</info>', $cleaned));

        return Command::SUCCESS;
    }
}
