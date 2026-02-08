<?php

namespace SmartCrm\DatabaseDumps\Service\Generator;

use SmartCrm\DatabaseDumps\Config\TableConfig;

/**
 * Главный генератор SQL дампов
 */
class SqlGenerator
{
    private TruncateGenerator $truncateGenerator;
    private InsertGenerator $insertGenerator;
    private SequenceGenerator $sequenceGenerator;

    public function __construct(
        TruncateGenerator $truncateGenerator,
        InsertGenerator $insertGenerator,
        SequenceGenerator $sequenceGenerator
    ) {
        $this->truncateGenerator = $truncateGenerator;
        $this->insertGenerator = $insertGenerator;
        $this->sequenceGenerator = $sequenceGenerator;
    }

    /**
     * Сгенерировать полный SQL дамп таблицы
     *
     * @param TableConfig $config
     * @param array<array<string, mixed>> $rows
     * @return string
     */
    public function generate(TableConfig $config, array $rows): string
    {
        $schema = $config->getSchema();
        $table = $config->getTable();

        $sql = "-- Дамп таблицы: {$schema}.{$table}\n";
        $sql .= "-- Дата экспорта: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Количество записей: " . count($rows) . "\n";

        if ($config->isPartialExport()) {
            $sql .= "-- Режим: partial (limit {$config->getLimit()})\n";
        } else {
            $sql .= "-- Режим: full\n";
        }

        $sql .= "\n";

        // TRUNCATE
        $sql .= $this->truncateGenerator->generate($schema, $table);
        $sql .= "\n";

        // INSERT
        $sql .= $this->insertGenerator->generate($schema, $table, $rows);

        // Sequence reset
        $sql .= $this->sequenceGenerator->generate($schema, $table);

        return $sql;
    }
}
