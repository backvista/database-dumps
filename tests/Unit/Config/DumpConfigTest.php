<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Config\DumpConfig;

class DumpConfigTest extends TestCase
{
    private DumpConfig $config;

    protected function setUp(): void
    {
        $this->config = new DumpConfig(
            [
                'users' => ['users', 'roles'],
                'system' => ['settings']
            ],
            [
                'clients' => [
                    'clients' => ['limit' => 100, 'order_by' => 'created_at DESC'],
                    'clients_attr' => ['limit' => 500]
                ]
            ],
            ['sessions', 'cache']
        );
    }

    public function testGetFullExportTables(): void
    {
        $tables = $this->config->getFullExportTables('users');

        $this->assertCount(2, $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('roles', $tables);
    }

    public function testGetFullExportTablesForNonExistentSchema(): void
    {
        $tables = $this->config->getFullExportTables('nonexistent');

        $this->assertEmpty($tables);
    }

    public function testGetPartialExportTables(): void
    {
        $tables = $this->config->getPartialExportTables('clients');

        $this->assertCount(2, $tables);
        $this->assertArrayHasKey('clients', $tables);
        $this->assertArrayHasKey('clients_attr', $tables);
        $this->assertEquals(100, $tables['clients']['limit']);
    }

    public function testGetAllFullExportSchemas(): void
    {
        $schemas = $this->config->getAllFullExportSchemas();

        $this->assertCount(2, $schemas);
        $this->assertContains('users', $schemas);
        $this->assertContains('system', $schemas);
    }

    public function testGetAllPartialExportSchemas(): void
    {
        $schemas = $this->config->getAllPartialExportSchemas();

        $this->assertCount(1, $schemas);
        $this->assertContains('clients', $schemas);
    }

    public function testIsExcluded(): void
    {
        $this->assertTrue($this->config->isExcluded('sessions'));
        $this->assertTrue($this->config->isExcluded('cache'));
        $this->assertFalse($this->config->isExcluded('users'));
    }

    public function testGetTableConfigFromPartialExport(): void
    {
        $config = $this->config->getTableConfig('clients', 'clients');

        $this->assertNotNull($config);
        $this->assertEquals(100, $config['limit']);
        $this->assertEquals('created_at DESC', $config['order_by']);
    }

    public function testGetTableConfigFromFullExport(): void
    {
        $config = $this->config->getTableConfig('users', 'users');

        $this->assertNotNull($config);
        $this->assertEmpty($config);
    }

    public function testGetTableConfigForNonExistentTable(): void
    {
        $config = $this->config->getTableConfig('nonexistent', 'table');

        $this->assertNull($config);
    }
}
