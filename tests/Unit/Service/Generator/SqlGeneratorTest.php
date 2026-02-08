<?php

namespace SmartCrm\DatabaseDumps\Tests\Unit\Service\Generator;

use PHPUnit\Framework\TestCase;
use SmartCrm\DatabaseDumps\Config\TableConfig;
use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;
use SmartCrm\DatabaseDumps\Service\Generator\InsertGenerator;
use SmartCrm\DatabaseDumps\Service\Generator\SequenceGenerator;
use SmartCrm\DatabaseDumps\Service\Generator\SqlGenerator;
use SmartCrm\DatabaseDumps\Service\Generator\TruncateGenerator;

class SqlGeneratorTest extends TestCase
{
    private SqlGenerator $generator;

    protected function setUp(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);
        $connection->method('quote')->willReturnCallback(fn($value) => "'{$value}'");
        $connection->method('fetchFirstColumn')->willReturn([]);

        $truncateGenerator = new TruncateGenerator();
        $insertGenerator = new InsertGenerator($connection);
        $sequenceGenerator = new SequenceGenerator($connection);

        $this->generator = new SqlGenerator(
            $truncateGenerator,
            $insertGenerator,
            $sequenceGenerator
        );
    }

    public function testGenerateFullExport(): void
    {
        $config = new TableConfig('users', 'users');
        $rows = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2']
        ];

        $sql = $this->generator->generate($config, $rows);

        $this->assertStringContainsString('Дамп таблицы: users.users', $sql);
        $this->assertStringContainsString('Количество записей: 2', $sql);
        $this->assertStringContainsString('Режим: full', $sql);
        $this->assertStringContainsString('TRUNCATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('Сброс sequences', $sql);
    }

    public function testGeneratePartialExport(): void
    {
        $config = new TableConfig('clients', 'clients', limit: 100);
        $rows = [
            ['id' => 1, 'name' => 'Client 1']
        ];

        $sql = $this->generator->generate($config, $rows);

        $this->assertStringContainsString('Дамп таблицы: clients.clients', $sql);
        $this->assertStringContainsString('Режим: partial (limit 100)', $sql);
    }

    public function testGenerateIncludesTimestamp(): void
    {
        $config = new TableConfig('users', 'users');
        $rows = [];

        $sql = $this->generator->generate($config, $rows);

        $this->assertStringContainsString('Дата экспорта:', $sql);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $sql);
    }
}
