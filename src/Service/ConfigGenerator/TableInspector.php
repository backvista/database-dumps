<?php

namespace BackVista\DatabaseDumps\Service\ConfigGenerator;

use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;

/**
 * Инспекция БД: список таблиц, подсчёт строк, определение колонки сортировки
 */
class TableInspector
{
    /** @var DatabaseConnectionInterface */
    private $connection;

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Получить список всех пользовательских таблиц
     *
     * @return array<int, array{table_schema: string, table_name: string}>
     */
    public function listTables(): array
    {
        $platform = $this->connection->getPlatformName();

        if ($platform === 'postgresql' || $platform === 'pgsql') {
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
        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Подсчитать количество строк в таблице
     */
    public function countRows(string $schema, string $table): int
    {
        $fullTable = $this->quoteFullTable($schema, $table);
        $sql = "SELECT COUNT(*) AS cnt FROM {$fullTable}";

        $rows = $this->connection->fetchAllAssociative($sql);

        return (int) $rows[0]['cnt'];
    }

    /**
     * Определить колонку для ORDER BY (с направлением DESC)
     *
     * Приоритет: updated_at/update_at → created_at/create_at → id → первая колонка
     */
    public function detectOrderColumn(string $schema, string $table): string
    {
        $sql = "SELECT column_name FROM information_schema.columns "
            . "WHERE table_schema = " . $this->connection->quote($schema)
            . " AND table_name = " . $this->connection->quote($table)
            . " ORDER BY ordinal_position";

        $rows = $this->connection->fetchAllAssociative($sql);

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

    private function quoteFullTable(string $schema, string $table): string
    {
        $platform = $this->connection->getPlatformName();

        if ($platform === 'postgresql' || $platform === 'pgsql') {
            return '"' . $schema . '"."' . $table . '"';
        }

        return '`' . $schema . '`.`' . $table . '`';
    }
}
