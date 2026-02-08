<?php

namespace BackVista\DatabaseDumps\Service\Generator;

use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;

/**
 * Генерация INSERT statements с батчингом
 */
class InsertGenerator
{
    private const BATCH_SIZE = 1000;

    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(ConnectionRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Сгенерировать INSERT statements с батчингом
     *
     * @param string $schema
     * @param string $table
     * @param array<array<string, mixed>> $rows
     * @param string|null $connectionName
     * @return string
     */
    public function generate(string $schema, string $table, array $rows, ?string $connectionName = null): string
    {
        if (empty($rows)) {
            return "-- Таблица пуста, нет данных для импорта\n";
        }

        $platform = $this->registry->getPlatform($connectionName);
        $connection = $this->registry->getConnection($connectionName);

        $fullTable = $platform->getFullTableName($schema, $table);
        $batches = array_chunk($rows, self::BATCH_SIZE);
        $sql = '';
        $batchNum = 1;

        foreach ($batches as $batch) {
            $sql .= "-- Batch {$batchNum} (" . count($batch) . " rows)\n";
            $sql .= $this->generateBatchInsert($fullTable, $batch, $platform, $connection);
            $sql .= "\n";
            $batchNum++;
        }

        return $sql;
    }

    /**
     * Сгенерировать INSERT для одного батча
     *
     * @param string $fullTable
     * @param array<array<string, mixed>> $rows
     * @param \BackVista\DatabaseDumps\Contract\DatabasePlatformInterface $platform
     * @param \BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface $connection
     * @return string
     */
    private function generateBatchInsert(string $fullTable, array $rows, $platform, $connection): string
    {
        if (empty($rows)) {
            return '';
        }

        $columns = array_keys($rows[0]);
        $columnsList = implode(', ', array_map(function ($col) use ($platform) {
            return $platform->quoteIdentifier($col);
        }, $columns));

        $sql = "INSERT INTO {$fullTable} ({$columnsList}) VALUES\n";

        $values = [];
        foreach ($rows as $row) {
            $escapedValues = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $escapedValues[] = 'NULL';
                } else {
                    $escapedValues[] = $connection->quote($value);
                }
            }
            $values[] = '(' . implode(', ', $escapedValues) . ')';
        }

        $sql .= implode(",\n", $values) . ";\n";

        return $sql;
    }
}
