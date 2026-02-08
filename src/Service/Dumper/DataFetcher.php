<?php

namespace BackVista\DatabaseDumps\Service\Dumper;

use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Contract\DatabasePlatformInterface;

/**
 * Загрузка данных из таблицы
 */
class DataFetcher
{
    /** @var DatabaseConnectionInterface */
    private $connection;

    /** @var DatabasePlatformInterface */
    private $platform;

    public function __construct(DatabaseConnectionInterface $connection, DatabasePlatformInterface $platform)
    {
        $this->connection = $connection;
        $this->platform = $platform;
    }

    /**
     * Загрузить данные из таблицы
     *
     * @return array<array<string, mixed>>
     */
    public function fetch(TableConfig $config): array
    {
        $fullTable = $this->platform->getFullTableName($config->getSchema(), $config->getTable());
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
}
