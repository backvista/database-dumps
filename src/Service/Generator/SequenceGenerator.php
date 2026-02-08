<?php

namespace SmartCrm\DatabaseDumps\Service\Generator;

use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;

/**
 * Генерация сброса sequences (PostgreSQL)
 */
class SequenceGenerator
{
    private DatabaseConnectionInterface $connection;

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Сгенерировать statements для сброса sequences
     */
    public function generate(string $schema, string $table): string
    {
        $sql = "-- Сброс sequences\n";

        try {
            // Получаем sequences для таблицы
            $sequences = $this->connection->fetchFirstColumn(
                "SELECT pg_get_serial_sequence(:table_name, column_name) as sequence_name
                 FROM information_schema.columns
                 WHERE table_schema = :schema
                 AND table_name = :table
                 AND column_default LIKE 'nextval%'",
                [
                    'schema' => $schema,
                    'table' => $table,
                    'table_name' => $schema . '.' . $table
                ]
            );

            foreach ($sequences as $sequence) {
                if ($sequence) {
                    $sql .= "SELECT setval('{$sequence}', (SELECT COALESCE(MAX(id), 1) FROM \"{$schema}\".\"{$table}\"));\n";
                }
            }
        } catch (\Exception $e) {
            $sql .= "-- Ошибка получения sequences: " . $e->getMessage() . "\n";
        }

        return $sql;
    }
}
