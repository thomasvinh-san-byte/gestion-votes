<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Command\EmailProcessQueueCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputOption;

/**
 * Unit tests for EmailProcessQueueCommand.
 *
 * Tests command configuration only — the execute() path requires a live DB
 * via Application::config() and is covered by integration tests.
 */
class EmailProcessQueueCommandTest extends TestCase
{
    private EmailProcessQueueCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new EmailProcessQueueCommand();
    }

    public function testCommandNameIsEmailProcessQueue(): void
    {
        $this->assertSame('email:process-queue', $this->command->getName());
    }

    public function testCommandHasBatchSizeOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('batch-size'), 'Command must have --batch-size option');
    }

    public function testBatchSizeDefaultIsFifty(): void
    {
        $definition = $this->command->getDefinition();
        $option = $definition->getOption('batch-size');
        $this->assertSame('50', $option->getDefault(), '--batch-size default must be 50');
    }

    public function testBatchSizeOptionRequiresValue(): void
    {
        $definition = $this->command->getDefinition();
        $option = $definition->getOption('batch-size');
        $this->assertTrue($option->acceptValue(), '--batch-size must accept a value (VALUE_REQUIRED)');
    }

    public function testCommandHasRemindersOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('reminders'), 'Command must have --reminders option');
    }

    public function testRemindersOptionIsValueNone(): void
    {
        $definition = $this->command->getDefinition();
        $option = $definition->getOption('reminders');
        $this->assertFalse($option->acceptValue(), '--reminders must be a flag (VALUE_NONE), not require a value');
    }

    public function testBatchSizeOptionHasShortcutB(): void
    {
        $definition = $this->command->getDefinition();
        $option = $definition->getOption('batch-size');
        $this->assertSame('b', $option->getShortcut(), '--batch-size shortcut must be -b');
    }

    public function testRemindersOptionHasShortcutR(): void
    {
        $definition = $this->command->getDefinition();
        $option = $definition->getOption('reminders');
        $this->assertSame('r', $option->getShortcut(), '--reminders shortcut must be -r');
    }

    public function testCommandDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription(), 'Command must have a description');
    }
}
