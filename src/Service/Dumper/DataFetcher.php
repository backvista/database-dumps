<?php

namespace BackVista\DatabaseDumps\Service\Dumper;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\PlatformFactory;

/**
 * Загрузка данных из таблицы
 */
class DataFetcher
{
    /** @var ConnectionRegistryInterface */
    private $registry;

    /** @var CascadeWhereResolver */
    private $cascadeResolver;

    /** @var DumpConfig */
    private $dumpConfig;

    public function __construct(
        ConnectionRegistryInterface $registry,
        CascadeWhereResolver $cascadeResolver,
        DumpConfig $dumpConfig
    ) {
        $this->registry = $registry;
        $this->cascadeResolver = $cascadeResolver;
        $this->dumpConfig = $dumpConfig;
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

        // Resolve cascade WHERE if configured
        $cascadeWhere = null;
        if ($config->getCascadeFrom() !== null) {
            $cascadeWhere = $this->cascadeResolver->resolve($config, $this->dumpConfig);
        }

        // Build WHERE clause
        $existingWhere = $config->getWhere();
        if ($existingWhere !== null && $cascadeWhere !== null) {
            $sql .= " WHERE ({$existingWhere}) AND ({$cascadeWhere})";
        } elseif ($cascadeWhere !== null) {
            $sql .= " WHERE {$cascadeWhere}";
        } elseif ($existingWhere !== null) {
            $sql .= " WHERE {$existingWhere}";
        }

        if ($config->getOrderBy()) {
            $sql .= " ORDER BY {$config->getOrderBy()}";
        }

        if ($config->getLimit()) {
            $sql .= ' ' . $platform->getLimitSql($config->getLimit());
        }

        $rows = $connection->fetchAllAssociative($sql);

        $platformName = $connection->getPlatformName();
        if ($platformName === PlatformFactory::POSTGRESQL || $platformName === PlatformFactory::PGSQL) {
            $rows = $this->normalizeBooleanColumns($connection, $config->getSchema(), $config->getTable(), $rows);
        }

        return $rows;
    }

    /**
     * Нормализация boolean-колонок PostgreSQL в PHP boolean
     *
     * PostgreSQL PDO возвращает boolean как строки 't'/'f' (PHP < 8.1)
     * или PHP boolean (PHP >= 8.1). Приводим к единому формату.
     *
     * @param DatabaseConnectionInterface $connection
     * @param string $schema
     * @param string $table
     * @param array<array<string, mixed>> $rows
     * @return array<array<string, mixed>>
     */
    private function normalizeBooleanColumns(
        DatabaseConnectionInterface $connection,
        $schema,
        $table,
        array $rows
    ) {
        if (empty($rows)) {
            return $rows;
        }

        $sql = sprintf(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = '%s' AND table_name = '%s' AND data_type = 'boolean'",
            $schema,
            $table
        );
        $boolColumnRows = $connection->fetchAllAssociative($sql);

        $boolColumns = array();
        foreach ($boolColumnRows as $row) {
            $boolColumns[] = $row['column_name'];
        }

        if (empty($boolColumns)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            foreach ($boolColumns as $col) {
                if (array_key_exists($col, $row) && $row[$col] !== null) {
                    $row[$col] = ($row[$col] === 't' || $row[$col] === true || $row[$col] === '1' || $row[$col] === 1);
                }
            }
        }
        unset($row);

        return $rows;
    }
}
