<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\MySqlPlatform;

class MySqlPlatformTest extends TestCase
{
    /** @var MySqlPlatform */
    private $platform;

    protected function setUp(): void
    {
        $this->platform = new MySqlPlatform();
    }

    public function testQuoteIdentifierUsesBackticks(): void
    {
        $this->assertEquals('`users`', $this->platform->quoteIdentifier('users'));
        $this->assertEquals('`my_table`', $this->platform->quoteIdentifier('my_table'));
    }

    public function testGetFullTableName(): void
    {
        $this->assertEquals('`users`.`users`', $this->platform->getFullTableName('users', 'users'));
        $this->assertEquals('`mydb`.`orders`', $this->platform->getFullTableName('mydb', 'orders'));
    }

    public function testGetTruncateStatementNoCascade(): void
    {
        $sql = $this->platform->getTruncateStatement('users', 'users');

        $this->assertStringNotContainsString('CASCADE', $sql);
        $this->assertStringContainsString('TRUNCATE TABLE `users`.`users`', $sql);
    }

    public function testGetTruncateStatementDisablesForeignKeyChecks(): void
    {
        $sql = $this->platform->getTruncateStatement('users', 'users');

        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1', $sql);
    }

    public function testGetSequenceResetSqlUsesAutoIncrement(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);

        $sql = $this->platform->getSequenceResetSql('users', 'users', $connection);

        $this->assertStringContainsString('AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`users`.`users`', $sql);
    }

    public function testGetSequenceResetSqlDoesNotContainSetval(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);

        $sql = $this->platform->getSequenceResetSql('users', 'users', $connection);

        $this->assertStringNotContainsString('setval', $sql);
        $this->assertStringNotContainsString('pg_get_serial_sequence', $sql);
    }

    public function testGetRandomFunctionSql(): void
    {
        $this->assertEquals('RAND()', $this->platform->getRandomFunctionSql());
    }
}
