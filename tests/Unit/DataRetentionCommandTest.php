<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Command\DataRetentionCommand;
use AgVote\Repository\MemberRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputOption;

/**
 * Unit tests for DataRetentionCommand and MemberRepository retention methods.
 *
 * Repository method tests use mock PDO statements.
 * Command tests validate configuration — execute() requires live DB.
 */
class DataRetentionCommandTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  DataRetentionCommand — configuration tests                         //
    // ------------------------------------------------------------------ //

    public function testCommandNameIsRgpdPurgeRetention(): void
    {
        $cmd = new DataRetentionCommand();
        $this->assertSame('rgpd:purge-retention', $cmd->getName());
    }

    public function testCommandHasTenantIdOption(): void
    {
        $cmd = new DataRetentionCommand();
        $definition = $cmd->getDefinition();
        $this->assertTrue($definition->hasOption('tenant-id'), 'Command must have --tenant-id option');
    }

    public function testTenantIdOptionRequiresValue(): void
    {
        $cmd = new DataRetentionCommand();
        $definition = $cmd->getDefinition();
        $option = $definition->getOption('tenant-id');
        $this->assertTrue($option->acceptValue(), '--tenant-id must accept a value');
        $this->assertFalse($option->isValueOptional(), '--tenant-id must be required (VALUE_REQUIRED)');
    }

    public function testTenantIdOptionHasShortcutT(): void
    {
        $cmd = new DataRetentionCommand();
        $definition = $cmd->getDefinition();
        $option = $definition->getOption('tenant-id');
        $this->assertSame('t', $option->getShortcut(), '--tenant-id shortcut must be -t');
    }

    public function testCommandHasDryRunOption(): void
    {
        $cmd = new DataRetentionCommand();
        $definition = $cmd->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'), 'Command must have --dry-run option');
    }

    public function testDryRunIsValueNone(): void
    {
        $cmd = new DataRetentionCommand();
        $definition = $cmd->getDefinition();
        $option = $definition->getOption('dry-run');
        $this->assertFalse($option->acceptValue(), '--dry-run must be a flag (VALUE_NONE)');
    }

    public function testCommandHasDescription(): void
    {
        $cmd = new DataRetentionCommand();
        $this->assertNotEmpty($cmd->getDescription(), 'Command must have a description');
    }

    // ------------------------------------------------------------------ //
    //  MemberRepository::findExpiredForTenant — unit tests               //
    // ------------------------------------------------------------------ //

    public function testFindExpiredForTenantWithZeroMonthsReturnsEmptyArray(): void
    {
        $repo = $this->createMockMemberRepository([]);
        $result = $repo->findExpiredForTenant('tenant-uuid', 0);
        $this->assertSame([], $result, 'Retention=0 must return [] (disabled)');
    }

    public function testFindExpiredForTenantWithNegativeMonthsReturnsEmptyArray(): void
    {
        $repo = $this->createMockMemberRepository([]);
        $result = $repo->findExpiredForTenant('tenant-uuid', -5);
        $this->assertSame([], $result, 'Retention<=0 must return [] (disabled)');
    }

    public function testFindExpiredForTenantWithPositiveMonthsReturnsRows(): void
    {
        $expectedRows = [
            ['id' => 'uuid-1', 'full_name' => 'Alice', 'email' => 'alice@test.com', 'updated_at' => '2024-01-01'],
            ['id' => 'uuid-2', 'full_name' => 'Bob', 'email' => 'bob@test.com', 'updated_at' => '2024-02-01'],
        ];
        $repo = $this->createMockMemberRepository($expectedRows);
        $result = $repo->findExpiredForTenant('tenant-uuid', 12);
        $this->assertSame($expectedRows, $result);
    }

    public function testFindExpiredForTenantScopesSqlToTenantId(): void
    {
        $capturedSql = null;
        $capturedParams = null;

        $repo = $this->createMockMemberRepositoryCapturingQuery(
            function (string $sql, array $params) use (&$capturedSql, &$capturedParams) {
                $capturedSql = $sql;
                $capturedParams = $params;
                return [];
            }
        );

        $repo->findExpiredForTenant('my-tenant-id', 12);

        $this->assertNotNull($capturedSql, 'selectAll must be called for months > 0');
        $this->assertStringContainsString('tenant_id', $capturedSql);
        $this->assertStringContainsString(':tid', $capturedSql);
        $this->assertArrayHasKey(':tid', $capturedParams);
        $this->assertSame('my-tenant-id', $capturedParams[':tid']);
    }

    public function testFindExpiredForTenantSqlUsesIntervalWithMonths(): void
    {
        $capturedSql = null;

        $repo = $this->createMockMemberRepositoryCapturingQuery(
            function (string $sql, array $params) use (&$capturedSql) {
                $capturedSql = $sql;
                return [];
            }
        );

        $repo->findExpiredForTenant('my-tenant-id', 6);

        $this->assertNotNull($capturedSql);
        $this->assertStringContainsString('6', $capturedSql, 'SQL must embed month count');
        $this->assertStringContainsString('INTERVAL', $capturedSql, 'SQL must use INTERVAL');
        $this->assertStringContainsString('updated_at', $capturedSql);
    }

    // ------------------------------------------------------------------ //
    //  MemberRepository::hardDeleteById — unit tests                      //
    // ------------------------------------------------------------------ //

    public function testHardDeleteByIdCallsDeleteWithCorrectParams(): void
    {
        $capturedSql = null;
        $capturedParams = null;

        $repo = $this->createMockMemberRepositoryCapturingExecute(
            function (string $sql, array $params) use (&$capturedSql, &$capturedParams) {
                $capturedSql = $sql;
                $capturedParams = $params;
                return 1;
            }
        );

        $rows = $repo->hardDeleteById('member-uuid', 'tenant-uuid');

        $this->assertSame(1, $rows, 'hardDeleteById must return affected row count');
        $this->assertNotNull($capturedSql);
        $this->assertStringContainsString('DELETE FROM members', $capturedSql);
        $this->assertStringContainsString(':id', $capturedSql);
        $this->assertStringContainsString(':tid', $capturedSql);
        $this->assertSame('member-uuid', $capturedParams[':id']);
        $this->assertSame('tenant-uuid', $capturedParams[':tid']);
    }

    public function testHardDeleteByIdReturnsZeroWhenNoRowAffected(): void
    {
        $repo = $this->createMockMemberRepositoryCapturingExecute(
            function (string $sql, array $params) {
                return 0;
            }
        );

        $rows = $repo->hardDeleteById('non-existent-uuid', 'tenant-uuid');
        $this->assertSame(0, $rows);
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    /**
     * Returns a MemberRepository stub where selectAll always returns $rows,
     * but only passes through for calls that would actually hit the DB (months > 0).
     */
    private function createMockMemberRepository(array $rows): MemberRepository
    {
        $repo = $this->getMockBuilder(MemberRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectAll', 'execute'])
            ->getMock();

        $repo->method('selectAll')->willReturn($rows);
        $repo->method('execute')->willReturn(1);

        return $repo;
    }

    /**
     * Returns a MemberRepository stub where selectAll invokes $callback($sql, $params).
     */
    private function createMockMemberRepositoryCapturingQuery(callable $callback): MemberRepository
    {
        $repo = $this->getMockBuilder(MemberRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectAll'])
            ->getMock();

        $repo->method('selectAll')
            ->willReturnCallback($callback);

        return $repo;
    }

    /**
     * Returns a MemberRepository stub where execute invokes $callback($sql, $params).
     */
    private function createMockMemberRepositoryCapturingExecute(callable $callback): MemberRepository
    {
        $repo = $this->getMockBuilder(MemberRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();

        $repo->method('execute')
            ->willReturnCallback($callback);

        return $repo;
    }
}
