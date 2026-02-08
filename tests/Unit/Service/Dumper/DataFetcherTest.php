<?php

namespace SmartCrm\DatabaseDumps\Tests\Unit\Service\Dumper;

use PHPUnit\Framework\TestCase;
use SmartCrm\DatabaseDumps\Config\TableConfig;
use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;
use SmartCrm\DatabaseDumps\Service\Dumper\DataFetcher;

class DataFetcherTest extends TestCase
{
    private DatabaseConnectionInterface $connection;
    private DataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $this->fetcher = new DataFetcher($this->connection);
    }

    public function testFetchFullExport(): void
    {
        $config = new TableConfig('users', 'users');

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('SELECT * FROM "users"."users"'))
            ->willReturn([
                ['id' => 1, 'name' => 'User 1'],
                ['id' => 2, 'name' => 'User 2']
            ]);

        $rows = $this->fetcher->fetch($config);

        $this->assertCount(2, $rows);
        $this->assertEquals('User 1', $rows[0]['name']);
    }

    public function testFetchWithLimit(): void
    {
        $config = new TableConfig('clients', 'clients', limit: 100);

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('LIMIT 100'))
            ->willReturn([]);

        $this->fetcher->fetch($config);
    }

    public function testFetchWithWhere(): void
    {
        $config = new TableConfig('clients', 'clients', where: 'is_active = true');

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('WHERE is_active = true'))
            ->willReturn([]);

        $this->fetcher->fetch($config);
    }

    public function testFetchWithOrderBy(): void
    {
        $config = new TableConfig('clients', 'clients', orderBy: 'created_at DESC');

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('ORDER BY created_at DESC'))
            ->willReturn([]);

        $this->fetcher->fetch($config);
    }

    public function testFetchWithAllOptions(): void
    {
        $config = new TableConfig(
            'clients',
            'clients',
            limit: 100,
            where: 'is_active = true',
            orderBy: 'created_at DESC'
        );

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->logicalAnd(
                $this->stringContains('WHERE is_active = true'),
                $this->stringContains('ORDER BY created_at DESC'),
                $this->stringContains('LIMIT 100')
            ))
            ->willReturn([]);

        $this->fetcher->fetch($config);
    }
}
