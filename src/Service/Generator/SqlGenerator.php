<?php

namespace BackVista\DatabaseDumps\Service\Generator;

use BackVista\DatabaseDumps\Config\TableConfig;

/**
 * Главный генератор SQL дампов
 */
class SqlGenerator
{
    /** @var TruncateGenerator */
    private $truncateGenerator;
    /** @var InsertGenerator */
    private $insertGenerator;
    /** @var SequenceGenerator */
    private $sequenceGenerator;

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
        $connectionName = $config->getConnectionName();

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
        $sql .= $this->truncateGenerator->generate($schema, $table, $connectionName);
        $sql .= "\n";

        // INSERT
        $sql .= $this->insertGenerator->generate($schema, $table, $rows, $connectionName);

        // Sequence reset
        $sql .= $this->sequenceGenerator->generate($schema, $table, $connectionName);

        return $sql;
    }

    /**
     * Потоковая генерация SQL дампа таблицы (Generator для экономии памяти)
     *
     * @param TableConfig $config
     * @param array<array<string, mixed>> $rows
     * @return \Generator<string>
     */
    public function generateChunks(TableConfig $config, array $rows)
    {
        $schema = $config->getSchema();
        $table = $config->getTable();
        $connectionName = $config->getConnectionName();

        $header = "-- Дамп таблицы: {$schema}.{$table}\n";
        $header .= "-- Дата экспорта: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- Количество записей: " . count($rows) . "\n";

        if ($config->isPartialExport()) {
            $header .= "-- Режим: partial (limit {$config->getLimit()})\n";
        } else {
            $header .= "-- Режим: full\n";
        }

        $header .= "\n";

        $header .= $this->truncateGenerator->generate($schema, $table, $connectionName);
        $header .= "\n";

        yield $header;

        foreach ($this->insertGenerator->generateChunks($schema, $table, $rows, $connectionName) as $chunk) {
            yield $chunk;
        }

        $footer = $this->sequenceGenerator->generate($schema, $table, $connectionName);
        if ($footer !== '') {
            yield $footer;
        }
    }
}
