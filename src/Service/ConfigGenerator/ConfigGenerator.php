<?php

namespace BackVista\DatabaseDumps\Service\ConfigGenerator;

use BackVista\DatabaseDumps\Contract\FileSystemInterface;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Генератор dump_config.yaml на основе текущей структуры БД
 */
class ConfigGenerator
{
    /** @var TableInspector */
    private $inspector;

    /** @var ServiceTableFilter */
    private $filter;

    /** @var FileSystemInterface */
    private $fileSystem;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        TableInspector $inspector,
        ServiceTableFilter $filter,
        FileSystemInterface $fileSystem,
        LoggerInterface $logger
    ) {
        $this->inspector = $inspector;
        $this->filter = $filter;
        $this->fileSystem = $fileSystem;
        $this->logger = $logger;
    }

    /**
     * Сгенерировать конфигурацию и записать в файл
     *
     * @return array{full: int, partial: int, skipped: int, empty: int}
     */
    public function generate(string $outputPath, int $threshold = 500): array
    {
        $stats = ['full' => 0, 'partial' => 0, 'skipped' => 0, 'empty' => 0];

        /** @var array<string, array<string>> $fullExport */
        $fullExport = [];

        /** @var array<string, array<string, array<string, mixed>>> $partialExport */
        $partialExport = [];

        $tables = $this->inspector->listTables();

        foreach ($tables as $tableInfo) {
            $schema = $tableInfo['table_schema'];
            $table = $tableInfo['table_name'];

            if ($this->filter->shouldIgnore($table)) {
                $this->logger->debug("Пропуск служебной таблицы: {$schema}.{$table}");
                $stats['skipped']++;
                continue;
            }

            $count = $this->inspector->countRows($schema, $table);

            if ($count === 0) {
                $this->logger->debug("Пропуск пустой таблицы: {$schema}.{$table}");
                $stats['empty']++;
                continue;
            }

            if ($count <= $threshold) {
                $this->logger->info("full_export: {$schema}.{$table} ({$count} строк)");
                if (!isset($fullExport[$schema])) {
                    $fullExport[$schema] = [];
                }
                $fullExport[$schema][] = $table;
                $stats['full']++;
            } else {
                $orderBy = $this->inspector->detectOrderColumn($schema, $table);
                $this->logger->info("partial_export: {$schema}.{$table} ({$count} строк, limit: {$threshold})");
                if (!isset($partialExport[$schema])) {
                    $partialExport[$schema] = [];
                }
                $partialExport[$schema][$table] = [
                    'limit' => $threshold,
                    'order_by' => $orderBy,
                ];
                $stats['partial']++;
            }
        }

        $config = [];
        if (!empty($fullExport)) {
            $config['full_export'] = $fullExport;
        }
        if (!empty($partialExport)) {
            $config['partial_export'] = $partialExport;
        }

        $yaml = Yaml::dump($config, 4, 2);
        $this->fileSystem->write($outputPath, $yaml);

        return $stats;
    }
}
