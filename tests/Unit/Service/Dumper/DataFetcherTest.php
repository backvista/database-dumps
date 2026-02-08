<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Dumper;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;
use BackVista\DatabaseDumps\Service\Dumper\DataFetcher;

class DataFetcherTest extends TestCase
{
    /** @var DatabaseConnectionInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $connection;

    /** @var DataFetcher */
    private $fetcher;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $platform = new PostgresPlatform();

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn($platform);

        $this->fetcher = new DataFetcher($registry);
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
        $config = new TableConfig('clients', 'clients', 100);

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('LIMIT 100'))
            ->willReturn([]);

        $this->fetcher->fetch($config);
    }

    public function testFetchWithWhere(): void
    {
        $config = new TableConfig('clients', 'clients', null, 'is_active = true');

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('WHERE is_active = true'))
            ->willReturn([]);

        $this->fetcher->fetch($config);
    }

    public function testFetchWithOrderBy(): void
    {
        $config = new TableConfig('clients', 'clients', null, null, 'created_at DESC');

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
            100,
            'is_active = true',
            'created_at DESC'
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
