<?php

namespace SmartCrm\DatabaseDumps\Service\Dumper;

use SmartCrm\DatabaseDumps\Config\TableConfig;
use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;

/**
 * Загрузка данных из таблицы
 */
class DataFetcher
{
    private DatabaseConnectionInterface $connection;

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Загрузить данные из таблицы
     *
     * @return array<array<string, mixed>>
     */
    public function fetch(TableConfig $config): array
    {
        $fullTable = $this->quoteIdentifier($config->getSchema(), $config->getTable());
        $sql = "SELECT * FROM {$fullTable}";

        if ($config->getWhere()) {
            $sql .= " WHERE {$config->getWhere()}";
        }

        if ($config->getOrderBy()) {
            $sql .= " ORDER BY {$config->getOrderBy()}";
        }

        if ($config->getLimit()) {
            $sql .= " LIMIT {$config->getLimit()}";
        }

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Экранировать идентификатор (схема.таблица)
     */
    private function quoteIdentifier(string $schema, string $table): string
    {
        return "\"{$schema}\".\"{$table}\"";
    }
}
