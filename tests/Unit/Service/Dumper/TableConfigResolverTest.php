<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Dumper;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Service\Dumper\TableConfigResolver;

class TableConfigResolverTest extends TestCase
{
    private TableConfigResolver $resolver;

    protected function setUp(): void
    {
        $dumpConfig = new DumpConfig(
            [
                'users' => ['users', 'roles'],
                'system' => ['settings']
            ],
            [
                'clients' => [
                    'clients' => ['limit' => 100, 'order_by' => 'created_at DESC']
                ]
            ]
        );

        $this->resolver = new TableConfigResolver($dumpConfig);
    }

    public function testResolveFullExportTable(): void
    {
        $config = $this->resolver->resolve('users', 'users');

        $this->assertEquals('users', $config->getSchema());
        $this->assertEquals('users', $config->getTable());
        $this->assertTrue($config->isFullExport());
        $this->assertNull($config->getLimit());
    }

    public function testResolvePartialExportTable(): void
    {
        $config = $this->resolver->resolve('clients', 'clients');

        $this->assertEquals('clients', $config->getSchema());
        $this->assertEquals('clients', $config->getTable());
        $this->assertTrue($config->isPartialExport());
        $this->assertEquals(100, $config->getLimit());
        $this->assertEquals('created_at DESC', $config->getOrderBy());
    }

    public function testResolveAllFromSchema(): void
    {
        $tables = $this->resolver->resolveAllFromSchema('users');

        $this->assertCount(2, $tables);

        $tableNames = array_map(fn($config) => $config->getTable(), $tables);
        $this->assertContains('users', $tableNames);
        $this->assertContains('roles', $tableNames);
    }

    public function testResolveAll(): void
    {
        $tables = $this->resolver->resolveAll();

        $this->assertGreaterThanOrEqual(3, count($tables));

        $fullNames = array_map(fn($config) => $config->getFullTableName(), $tables);
        $this->assertContains('users.users', $fullNames);
        $this->assertContains('clients.clients', $fullNames);
    }

    public function testResolveAllWithSchemaFilter(): void
    {
        $tables = $this->resolver->resolveAll('users');

        $this->assertCount(2, $tables);

        foreach ($tables as $table) {
            $this->assertEquals('users', $table->getSchema());
        }
    }
}
