<?php

namespace BackVista\DatabaseDumps\Service\ConfigGenerator;

use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\PlatformFactory;

/**
 * Инспекция БД: список таблиц, подсчёт строк, определение колонки сортировки
 */
class TableInspector
{
    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(ConnectionRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Получить список всех пользовательских таблиц
     *
     * @return array<int, array{table_schema: string, table_name: string}>
     */
    public function listTables(?string $connectionName = null): array
    {
        $connection = $this->registry->getConnection($connectionName);

        return $this->listTablesFor($connection);
    }

    /**
     * Подсчитать количество строк в таблице
     */
    public function countRows(string $schema, string $table, ?string $connectionName = null): int
    {
        $connection = $this->registry->getConnection($connectionName);
        $fullTable = $this->quoteFullTable($schema, $table, $connection);
        $sql = "SELECT COUNT(*) AS cnt FROM {$fullTable}";

        $rows = $connection->fetchAllAssociative($sql);

        return (int) $rows[0]['cnt'];
    }

    /**
     * Определить колонку для ORDER BY (с направлением DESC)
     *
     * Приоритет: updated_at/update_at → created_at/create_at → id → первая колонка
     */
    public function detectOrderColumn(string $schema, string $table, ?string $connectionName = null): string
    {
        $connection = $this->registry->getConnection($connectionName);

        $sql = "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = " . $connection->quote($schema)
            . " AND table_name = " . $connection->quote($table)
            . " ORDER BY ordinal_position";

        $rows = $connection->fetchAllAssociative($sql);

        $columns = [];
        foreach ($rows as $row) {
            $columns[] = $row['column_name'];
        }

        $priority = ['updated_at', 'update_at', 'created_at', 'create_at', 'id'];

        foreach ($priority as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate . ' DESC';
            }
        }

        if (!empty($columns)) {
            return $columns[0] . ' DESC';
        }

        return 'id DESC';
    }

    /**
     * @return array<int, array{table_schema: string, table_name: string}>
     */
    private function listTablesFor(DatabaseConnectionInterface $connection): array
    {
        $platform = $connection->getPlatformName();

        if ($platform === PlatformFactory::POSTGRESQL || $platform === PlatformFactory::PGSQL) {
            $sql = "SELECT table_schema, table_name FROM information_schema.tables "
                . "WHERE table_schema NOT IN ('pg_catalog', 'information_schema') "
                . "AND table_type = 'BASE TABLE' "
                . "ORDER BY table_schema, table_name";
        } else {
            $sql = "SELECT table_schema, table_name FROM information_schema.tables "
                . "WHERE table_schema NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys') "
                . "AND table_type = 'BASE TABLE' "
                . "ORDER BY table_schema, table_name";
        }

        /** @var array<int, array{table_schema: string, table_name: string}> */
        return $connection->fetchAllAssociative($sql);
    }

    private function quoteFullTable(string $schema, string $table, DatabaseConnectionInterface $connection): string
    {
        $platform = $connection->getPlatformName();

        if ($platform === PlatformFactory::POSTGRESQL || $platform === PlatformFactory::PGSQL) {
            return '"' . $schema . '"."' . $table . '"';
        }

        return '`' . $schema . '`.`' . $table . '`';
    }
}
