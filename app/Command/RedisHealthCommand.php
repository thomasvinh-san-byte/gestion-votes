<?php
declare(strict_types=1);

namespace AgVote\Command;

use AgVote\Core\Providers\RedisProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'redis:health',
    description: 'Check Redis connectivity and display info',
)]
final class RedisHealthCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!RedisProvider::isAvailable()) {
            $output->writeln('<comment>Redis extension (phpredis) is not installed. Falling back to file-based backends.</comment>');
            return Command::SUCCESS;
        }

        try {
            $redis = RedisProvider::connection();
            $info = $redis->info('server');

            $output->writeln('<info>Redis connection OK</info>');
            $output->writeln(sprintf('  Version: %s', $info['redis_version'] ?? 'unknown'));
            $output->writeln(sprintf('  Uptime: %s seconds', $info['uptime_in_seconds'] ?? '?'));
            $output->writeln(sprintf('  Connected clients: %s', $redis->info('clients')['connected_clients'] ?? '?'));

            $dbSize = $redis->dbSize();
            $output->writeln(sprintf('  Keys in DB: %d', $dbSize));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Redis connection failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
