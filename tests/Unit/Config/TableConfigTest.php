<?php

namespace SmartCrm\DatabaseDumps\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SmartCrm\DatabaseDumps\Config\TableConfig;

class TableConfigTest extends TestCase
{
    public function testFullExportConfig(): void
    {
        $config = new TableConfig('users', 'users');

        $this->assertEquals('users', $config->getSchema());
        $this->assertEquals('users', $config->getTable());
        $this->assertEquals('users.users', $config->getFullTableName());
        $this->assertNull($config->getLimit());
        $this->assertNull($config->getWhere());
        $this->assertNull($config->getOrderBy());
        $this->assertTrue($config->isFullExport());
        $this->assertFalse($config->isPartialExport());
    }

    public function testPartialExportConfig(): void
    {
        $config = new TableConfig(
            schema: 'clients',
            table: 'clients',
            limit: 100,
            where: 'is_active = true',
            orderBy: 'created_at DESC'
        );

        $this->assertEquals('clients', $config->getSchema());
        $this->assertEquals('clients', $config->getTable());
        $this->assertEquals('clients.clients', $config->getFullTableName());
        $this->assertEquals(100, $config->getLimit());
        $this->assertEquals('is_active = true', $config->getWhere());
        $this->assertEquals('created_at DESC', $config->getOrderBy());
        $this->assertFalse($config->isFullExport());
        $this->assertTrue($config->isPartialExport());
    }

    public function testFromArrayFullExport(): void
    {
        $config = TableConfig::fromArray('users', 'users', []);

        $this->assertEquals('users', $config->getSchema());
        $this->assertEquals('users', $config->getTable());
        $this->assertTrue($config->isFullExport());
    }

    public function testFromArrayPartialExport(): void
    {
        $config = TableConfig::fromArray('clients', 'clients', [
            'limit' => 100,
            'where' => 'is_active = true',
            'order_by' => 'created_at DESC'
        ]);

        $this->assertEquals('clients', $config->getSchema());
        $this->assertEquals('clients', $config->getTable());
        $this->assertEquals(100, $config->getLimit());
        $this->assertEquals('is_active = true', $config->getWhere());
        $this->assertEquals('created_at DESC', $config->getOrderBy());
        $this->assertTrue($config->isPartialExport());
    }
}
