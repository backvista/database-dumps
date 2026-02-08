<?php

namespace SmartCrm\DatabaseDumps\Tests\Unit\Service\Generator;

use PHPUnit\Framework\TestCase;
use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;
use SmartCrm\DatabaseDumps\Service\Generator\InsertGenerator;

class InsertGeneratorTest extends TestCase
{
    private DatabaseConnectionInterface $connection;
    private InsertGenerator $generator;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $this->connection->method('quote')->willReturnCallback(fn($value) => "'{$value}'");

        $this->generator = new InsertGenerator($this->connection);
    }

    public function testGenerateWithEmptyRows(): void
    {
        $sql = $this->generator->generate('users', 'users', []);

        $this->assertStringContainsString('Таблица пуста', $sql);
        $this->assertStringNotContainsString('INSERT', $sql);
    }

    public function testGenerateWithSingleRow(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com']
        ];

        $sql = $this->generator->generate('users', 'users', $rows);

        $this->assertStringContainsString('INSERT INTO "users"."users"', $sql);
        $this->assertStringContainsString('"id", "name", "email"', $sql);
        $this->assertStringContainsString("'1'", $sql);
        $this->assertStringContainsString("'User 1'", $sql);
        $this->assertStringContainsString("'user1@example.com'", $sql);
    }

    public function testGenerateWithMultipleRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
            ['id' => 3, 'name' => 'User 3']
        ];

        $sql = $this->generator->generate('users', 'users', $rows);

        $this->assertStringContainsString('Batch 1 (3 rows)', $sql);
        $this->assertStringContainsString("'User 1'", $sql);
        $this->assertStringContainsString("'User 2'", $sql);
        $this->assertStringContainsString("'User 3'", $sql);
    }

    public function testGenerateHandlesNullValues(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'User 1', 'email' => null]
        ];

        $sql = $this->generator->generate('users', 'users', $rows);

        $this->assertStringContainsString('NULL', $sql);
    }

    public function testGenerateBatchesLargeDataset(): void
    {
        // Create 2500 rows to test batching (should create 3 batches: 1000, 1000, 500)
        $rows = [];
        for ($i = 1; $i <= 2500; $i++) {
            $rows[] = ['id' => $i, 'name' => "User {$i}"];
        }

        $sql = $this->generator->generate('users', 'users', $rows);

        $this->assertStringContainsString('Batch 1 (1000 rows)', $sql);
        $this->assertStringContainsString('Batch 2 (1000 rows)', $sql);
        $this->assertStringContainsString('Batch 3 (500 rows)', $sql);
    }

    public function testGenerateQuotesIdentifiers(): void
    {
        $rows = [['id' => 1, 'name' => 'Test']];

        $sql = $this->generator->generate('test_schema', 'test_table', $rows);

        $this->assertStringContainsString('"test_schema"."test_table"', $sql);
        $this->assertStringContainsString('"id"', $sql);
        $this->assertStringContainsString('"name"', $sql);
    }
}
