<?php

declare(strict_types=1);

namespace AgVote\Command;

// CLI tool — intentionally retained, no unit test required

use AgVote\Core\Security\RateLimiter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ratelimit:reset',
    description: 'Reset rate-limit counter for a specific context and identifier',
)]
final class RateLimitResetCommand extends Command {
    protected function configure(): void {
        $this->addOption('context', null, InputOption::VALUE_REQUIRED, 'Rate-limit context (e.g. login)');
        $this->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Identifier to reset (IP, email, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $context = $input->getOption('context');
        $identifier = $input->getOption('identifier');

        if (!$context || !$identifier) {
            $output->writeln('<error>Both --context and --identifier are required.</error>');
            return Command::FAILURE;
        }

        RateLimiter::reset($context, $identifier);
        $output->writeln(sprintf('<info>Rate-limit reset for context="%s" identifier="%s".</info>', $context, $identifier));

        return Command::SUCCESS;
    }
}
