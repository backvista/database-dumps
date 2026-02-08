<?php

namespace SmartCrm\DatabaseDumps\Service\Generator;

use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;

/**
 * Генерация INSERT statements с батчингом
 */
class InsertGenerator
{
    private const BATCH_SIZE = 1000;

    private DatabaseConnectionInterface $connection;

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Сгенерировать INSERT statements с батчингом
     *
     * @param string $schema
     * @param string $table
     * @param array<array<string, mixed>> $rows
     * @return string
     */
    public function generate(string $schema, string $table, array $rows): string
    {
        if (empty($rows)) {
            return "-- Таблица пуста, нет данных для импорта\n";
        }

        $fullTable = $this->quoteIdentifier($schema, $table);
        $batches = array_chunk($rows, self::BATCH_SIZE);
        $sql = '';
        $batchNum = 1;

        foreach ($batches as $batch) {
            $sql .= "-- Batch {$batchNum} (" . count($batch) . " rows)\n";
            $sql .= $this->generateBatchInsert($fullTable, $batch);
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
     * @return string
     */
    private function generateBatchInsert(string $fullTable, array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $columns = array_keys($rows[0]);
        $columnsList = implode(', ', array_map(fn($col) => "\"{$col}\"", $columns));

        $sql = "INSERT INTO {$fullTable} ({$columnsList}) VALUES\n";

        $values = [];
        foreach ($rows as $row) {
            $escapedValues = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $escapedValues[] = 'NULL';
                } else {
                    $escapedValues[] = $this->connection->quote($value);
                }
            }
            $values[] = '(' . implode(', ', $escapedValues) . ')';
        }

        $sql .= implode(",\n", $values) . ";\n";

        return $sql;
    }

    /**
     * Экранировать идентификатор (схема.таблица)
     */
    private function quoteIdentifier(string $schema, string $table): string
    {
        return "\"{$schema}\".\"{$table}\"";
    }
}
