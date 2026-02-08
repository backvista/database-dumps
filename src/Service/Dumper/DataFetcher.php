<?php

namespace BackVista\DatabaseDumps\Service\Dumper;

use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;

/**
 * Загрузка данных из таблицы
 */
class DataFetcher
{
    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(ConnectionRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Загрузить данные из таблицы
     *
     * @return array<array<string, mixed>>
     */
    public function fetch(TableConfig $config): array
    {
        $connectionName = $config->getConnectionName();
        $connection = $this->registry->getConnection($connectionName);
        $platform = $this->registry->getPlatform($connectionName);

        $fullTable = $platform->getFullTableName($config->getSchema(), $config->getTable());
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

        return $connection->fetchAllAssociative($sql);
    }
}
