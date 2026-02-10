<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Dumper;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;
use BackVista\DatabaseDumps\Service\Dumper\CascadeWhereResolver;
use PHPUnit\Framework\TestCase;

class CascadeWhereResolverTest extends TestCase
{
    /** @var CascadeWhereResolver */
    private $resolver;

    protected function setUp(): void
    {
        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());
        $this->resolver = new CascadeWhereResolver($registry);
    }

    public function testResolveReturnsNullWhenNoCascade(): void
    {
        $config = new TableConfig('public', 'users');
        $dumpConfig = new DumpConfig([], []);
        $this->assertNull($this->resolver->resolve($config, $dumpConfig));
    }

    public function testResolveReturnsNullWhenParentInFullExport(): void
    {
        $config = new TableConfig('public', 'orders', 500, null, null, null, [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
        ]);
        $dumpConfig = new DumpConfig(
            ['public' => ['users']],  // full_export
            []
        );
        $this->assertNull($this->resolver->resolve($config, $dumpConfig));
    }

    public function testResolveGeneratesSubqueryForPartialParent(): void
    {
        $config = new TableConfig('public', 'orders', 500, null, 'id DESC', null, [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
        ]);
        $dumpConfig = new DumpConfig(
            [],
            ['public' => [
                'users' => [
                    TableConfig::KEY_LIMIT => 100,
                    TableConfig::KEY_ORDER_BY => 'created_at DESC',
                    TableConfig::KEY_WHERE => 'is_active = true',
                ],
            ]]
        );
        $result = $this->resolver->resolve($config, $dumpConfig);
        $this->assertNotNull($result);
        $this->assertStringContainsString('user_id IN (SELECT "id" FROM "public"."users"', $result);
        $this->assertStringContainsString('WHERE is_active = true', $result);
        $this->assertStringContainsString('ORDER BY created_at DESC', $result);
        $this->assertStringContainsString('LIMIT 100', $result);
    }

    public function testResolveMultipleCascadesWithAnd(): void
    {
        $config = new TableConfig('public', 'order_items', 500, null, null, null, [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
            ['parent' => 'public.orders', 'fk_column' => 'order_id', 'parent_column' => 'id'],
        ]);
        $dumpConfig = new DumpConfig(
            [],
            ['public' => [
                'users' => [TableConfig::KEY_LIMIT => 100],
                'orders' => [TableConfig::KEY_LIMIT => 200],
            ]]
        );
        $result = $this->resolver->resolve($config, $dumpConfig);
        $this->assertNotNull($result);
        $this->assertStringContainsString('user_id IN (', $result);
        $this->assertStringContainsString('order_id IN (', $result);
        $this->assertStringContainsString(' AND ', $result);
    }

    public function testResolveReturnsNullWhenParentNotInConfig(): void
    {
        $config = new TableConfig('public', 'orders', 500, null, null, null, [
            ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
        ]);
        $dumpConfig = new DumpConfig([], []);
        $this->assertNull($this->resolver->resolve($config, $dumpConfig));
    }

    public function testResolveWithChainedCascade(): void
    {
        // order_items -> orders -> users (3 levels)
        $config = new TableConfig('public', 'order_items', 500, null, null, null, [
            ['parent' => 'public.orders', 'fk_column' => 'order_id', 'parent_column' => 'id'],
        ]);
        $dumpConfig = new DumpConfig(
            [],
            ['public' => [
                'users' => [
                    TableConfig::KEY_LIMIT => 100,
                    TableConfig::KEY_WHERE => 'is_active = true',
                ],
                'orders' => [
                    TableConfig::KEY_LIMIT => 200,
                    TableConfig::KEY_CASCADE_FROM => [
                        ['parent' => 'public.users', 'fk_column' => 'user_id', 'parent_column' => 'id'],
                    ],
                ],
            ]]
        );
        $result = $this->resolver->resolve($config, $dumpConfig);
        $this->assertNotNull($result);
        // Should have nested subquery
        $this->assertStringContainsString('order_id IN (SELECT "id" FROM "public"."orders"', $result);
        $this->assertStringContainsString('user_id IN (SELECT "id" FROM "public"."users"', $result);
    }
}
