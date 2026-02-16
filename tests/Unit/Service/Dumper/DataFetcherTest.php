<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Dumper;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\MySqlPlatform;
use BackVista\DatabaseDumps\Platform\OraclePlatform;
use BackVista\DatabaseDumps\Platform\PlatformFactory;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;
use BackVista\DatabaseDumps\Service\Dumper\CascadeWhereResolver;
use BackVista\DatabaseDumps\Service\Dumper\DataFetcher;

class DataFetcherTest extends TestCase
{
    /** @var DatabaseConnectionInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $connection;

    /** @var CascadeWhereResolver&\PHPUnit\Framework\MockObject\MockObject */
    private $cascadeResolver;

    /** @var DataFetcher */
    private $fetcher;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $platform = new PostgresPlatform();

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn($platform);

        $this->cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $this->cascadeResolver->method('resolve')->willReturn(null);

        $dumpConfig = new DumpConfig([], []);

        $this->fetcher = new DataFetcher($registry, $this->cascadeResolver, $dumpConfig);
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

    public function testFetchWithCascadeWhere(): void
    {
        $cascadeFrom = [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
        ];
        $config = new TableConfig('public', 'orders', null, null, null, null, $cascadeFrom);

        $cascadeWhereValue = 'user_id IN (SELECT "id" FROM "public"."users" WHERE active=true)';

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn($cascadeWhereValue);

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());

        $dumpConfig = new DumpConfig([], []);

        $fetcher = new DataFetcher($registry, $cascadeResolver, $dumpConfig);

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains($cascadeWhereValue))
            ->willReturn([]);

        $fetcher->fetch($config);
    }

    public function testFetchWithCascadeWhereAndExistingWhere(): void
    {
        $cascadeFrom = [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
        ];
        $config = new TableConfig('public', 'orders', null, 'status = 1', null, null, $cascadeFrom);

        $cascadeWhereValue = 'user_id IN (SELECT "id" FROM "public"."users" WHERE active=true)';

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn($cascadeWhereValue);

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());

        $dumpConfig = new DumpConfig([], []);

        $fetcher = new DataFetcher($registry, $cascadeResolver, $dumpConfig);

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->logicalAnd(
                $this->stringContains('WHERE (status = 1) AND (' . $cascadeWhereValue . ')'),
                $this->stringContains('SELECT * FROM "public"."orders"')
            ))
            ->willReturn([]);

        $fetcher->fetch($config);
    }

    public function testFetchWithLimitOracle(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);

        $platform = new OraclePlatform();

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getPlatform')->willReturn($platform);

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn(null);

        $dumpConfig = new DumpConfig([], []);
        $fetcher = new DataFetcher($registry, $cascadeResolver, $dumpConfig);

        $config = new TableConfig('clients', 'clients', 100);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('FETCH FIRST 100 ROWS ONLY'))
            ->willReturn([]);

        $fetcher->fetch($config);
    }

    public function testFetchNormalizesBooleanColumnsForPostgres(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);
        $connection->method('getPlatformName')->willReturn(PlatformFactory::POSTGRESQL);

        $connection->method('fetchAllAssociative')->willReturnCallback(
            function ($sql) {
                if (strpos($sql, 'information_schema') !== false) {
                    return [
                        ['column_name' => 'is_active'],
                        ['column_name' => 'is_deleted'],
                    ];
                }
                // PostgreSQL PDO (PHP < 8.1) возвращает boolean как строки 't'/'f'
                return [
                    ['id' => 1, 'name' => 'User 1', 'is_active' => 't', 'is_deleted' => 'f'],
                    ['id' => 2, 'name' => 'User 2', 'is_active' => 'f', 'is_deleted' => 't'],
                ];
            }
        );

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn(null);

        $fetcher = new DataFetcher($registry, $cascadeResolver, new DumpConfig([], []));

        $config = new TableConfig('users', 'users');
        $rows = $fetcher->fetch($config);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['is_active']);
        $this->assertFalse($rows[0]['is_deleted']);
        $this->assertFalse($rows[1]['is_active']);
        $this->assertTrue($rows[1]['is_deleted']);
        // Нe-boolean колонки не затронуты
        $this->assertSame('User 1', $rows[0]['name']);
    }

    public function testFetchNormalizesNativeBooleanForPostgres(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);
        $connection->method('getPlatformName')->willReturn(PlatformFactory::POSTGRESQL);

        $connection->method('fetchAllAssociative')->willReturnCallback(
            function ($sql) {
                if (strpos($sql, 'information_schema') !== false) {
                    return [['column_name' => 'is_active']];
                }
                // PHP >= 8.1 PDO возвращает нативные boolean
                return [
                    ['id' => 1, 'is_active' => true],
                    ['id' => 2, 'is_active' => false],
                ];
            }
        );

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn(null);

        $fetcher = new DataFetcher($registry, $cascadeResolver, new DumpConfig([], []));

        $rows = $fetcher->fetch(new TableConfig('users', 'users'));

        $this->assertTrue($rows[0]['is_active']);
        $this->assertFalse($rows[1]['is_active']);
    }

    public function testFetchPreservesNullBooleanForPostgres(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);
        $connection->method('getPlatformName')->willReturn(PlatformFactory::POSTGRESQL);

        $connection->method('fetchAllAssociative')->willReturnCallback(
            function ($sql) {
                if (strpos($sql, 'information_schema') !== false) {
                    return [['column_name' => 'is_active']];
                }
                return [['id' => 1, 'is_active' => null]];
            }
        );

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn(null);

        $fetcher = new DataFetcher($registry, $cascadeResolver, new DumpConfig([], []));

        $rows = $fetcher->fetch(new TableConfig('users', 'users'));

        $this->assertNull($rows[0]['is_active']);
    }

    public function testFetchDoesNotNormalizeBooleanForMysql(): void
    {
        $connection = $this->createMock(DatabaseConnectionInterface::class);
        $connection->method('getPlatformName')->willReturn(PlatformFactory::MYSQL);

        // fetchAllAssociative вызывается только один раз (без information_schema)
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 1, 'is_active' => 1],
            ]);

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getPlatform')->willReturn(new MySqlPlatform());

        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn(null);

        $fetcher = new DataFetcher($registry, $cascadeResolver, new DumpConfig([], []));

        $rows = $fetcher->fetch(new TableConfig('users', 'users'));

        $this->assertSame(1, $rows[0]['is_active']);
    }

    public function testFetchWithCascadeFromReturningNull(): void
    {
        $cascadeFrom = [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
        ];
        $config = new TableConfig('public', 'orders', null, 'status = 1', null, null, $cascadeFrom);

        // cascadeResolver returns null (parent is full export)
        $cascadeResolver = $this->createMock(CascadeWhereResolver::class);
        $cascadeResolver->method('resolve')->willReturn(null);

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());

        $dumpConfig = new DumpConfig([], []);

        $fetcher = new DataFetcher($registry, $cascadeResolver, $dumpConfig);

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->logicalAnd(
                $this->stringContains('WHERE status = 1'),
                $this->logicalNot($this->stringContains('AND'))
            ))
            ->willReturn([]);

        $fetcher->fetch($config);
    }
}
