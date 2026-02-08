<?php

namespace BackVista\DatabaseDumps\Service\ConfigGenerator;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\FileSystemInterface;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Генератор dump_config.yaml на основе текущей структуры БД
 */
class ConfigGenerator
{
    public const WHERE_HINT = 'where: "1=1"';

    /** @var TableInspector */
    private $inspector;

    /** @var ServiceTableFilter */
    private $filter;

    /** @var FileSystemInterface */
    private $fileSystem;

    /** @var LoggerInterface */
    private $logger;

    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(
        TableInspector $inspector,
        ServiceTableFilter $filter,
        FileSystemInterface $fileSystem,
        LoggerInterface $logger,
        ConnectionRegistryInterface $registry
    ) {
        $this->inspector = $inspector;
        $this->filter = $filter;
        $this->fileSystem = $fileSystem;
        $this->logger = $logger;
        $this->registry = $registry;
    }

    /**
     * Сгенерировать конфигурацию и записать в файл
     *
     * @return array{full: int, partial: int, skipped: int, empty: int}
     */
    public function generate(string $outputPath, int $threshold = 500): array
    {
        $totalStats = ['full' => 0, 'partial' => 0, 'skipped' => 0, 'empty' => 0];
        $config = [];

        // Дефолтное подключение
        $defaultStats = $this->generateForConnection(null, $threshold);
        $this->mergeStats($totalStats, $defaultStats['stats']);
        if (!empty($defaultStats[DumpConfig::KEY_FULL_EXPORT])) {
            $config[DumpConfig::KEY_FULL_EXPORT] = $defaultStats[DumpConfig::KEY_FULL_EXPORT];
        }
        if (!empty($defaultStats[DumpConfig::KEY_PARTIAL_EXPORT])) {
            $config[DumpConfig::KEY_PARTIAL_EXPORT] = $defaultStats[DumpConfig::KEY_PARTIAL_EXPORT];
        }

        // Дополнительные подключения
        $connectionNames = $this->registry->getNames();
        $defaultName = $this->registry->getDefaultName();
        $connectionConfigs = [];

        foreach ($connectionNames as $connName) {
            if ($connName === $defaultName) {
                continue;
            }

            $this->logger->info("Инспекция подключения: {$connName}");
            $connStats = $this->generateForConnection($connName, $threshold);
            $this->mergeStats($totalStats, $connStats['stats']);

            $connConfig = [];
            if (!empty($connStats[DumpConfig::KEY_FULL_EXPORT])) {
                $connConfig[DumpConfig::KEY_FULL_EXPORT] = $connStats[DumpConfig::KEY_FULL_EXPORT];
            }
            if (!empty($connStats[DumpConfig::KEY_PARTIAL_EXPORT])) {
                $connConfig[DumpConfig::KEY_PARTIAL_EXPORT] = $connStats[DumpConfig::KEY_PARTIAL_EXPORT];
            }

            if (!empty($connConfig)) {
                $connectionConfigs[$connName] = $connConfig;
            }
        }

        if (!empty($connectionConfigs)) {
            $config[DumpConfig::KEY_CONNECTIONS] = $connectionConfigs;
        }

        $yaml = Yaml::dump($config, 4, 2);
        $yaml = $this->addWhereHints($yaml);
        $this->fileSystem->write($outputPath, $yaml);

        return $totalStats;
    }

    /**
     * Сгенерировать конфигурацию для одного подключения
     *
     * @return array{full_export: array<string, array<string>>, partial_export: array<string, array<string, array<string, mixed>>>, stats: array{full: int, partial: int, skipped: int, empty: int}}
     */
    private function generateForConnection(?string $connectionName, int $threshold): array
    {
        $stats = ['full' => 0, 'partial' => 0, 'skipped' => 0, 'empty' => 0];

        /** @var array<string, array<string>> $fullExport */
        $fullExport = [];

        /** @var array<string, array<string, array<string, mixed>>> $partialExport */
        $partialExport = [];

        $tables = $this->inspector->listTables($connectionName);
        $total = count($tables);
        $current = 0;

        foreach ($tables as $tableInfo) {
            $current++;
            $schema = $tableInfo['table_schema'];
            $table = $tableInfo['table_name'];
            $prefix = "[{$current}/{$total}] {$schema}.{$table}";

            if ($this->filter->shouldIgnore($table)) {
                $this->logger->info("{$prefix} ... SKIP (служебная)");
                $stats['skipped']++;
                continue;
            }

            $count = $this->inspector->countRows($schema, $table, $connectionName);

            if ($count === 0) {
                $this->logger->info("{$prefix} ... SKIP (пустая)");
                $stats['empty']++;
                continue;
            }

            if ($count <= $threshold) {
                $this->logger->info("{$prefix} ... full_export ({$count} строк)");
                if (!isset($fullExport[$schema])) {
                    $fullExport[$schema] = [];
                }
                $fullExport[$schema][] = $table;
                $stats['full']++;
            } else {
                $orderBy = $this->inspector->detectOrderColumn($schema, $table, $connectionName);
                $this->logger->info("{$prefix} ... partial_export ({$count} строк, limit: {$threshold})");
                if (!isset($partialExport[$schema])) {
                    $partialExport[$schema] = [];
                }
                $partialExport[$schema][$table] = [
                    TableConfig::KEY_LIMIT => $threshold,
                    TableConfig::KEY_ORDER_BY => $orderBy,
                ];
                $stats['partial']++;
            }
        }

        return [
            DumpConfig::KEY_FULL_EXPORT => $fullExport,
            DumpConfig::KEY_PARTIAL_EXPORT => $partialExport,
            'stats' => $stats,
        ];
    }

    /**
     * Добавить закомментированные подсказки where после каждой строки order_by
     */
    private function addWhereHints(string $yaml): string
    {
        $lines = explode("\n", $yaml);
        $result = [];

        foreach ($lines as $line) {
            $result[] = $line;
            if (preg_match('/^(\s+)order_by:\s/', $line, $matches)) {
                $result[] = $matches[1] . self::WHERE_HINT;
            }
        }

        return implode("\n", $result);
    }

    /**
     * @param array{full: int, partial: int, skipped: int, empty: int} $total
     * @param array{full: int, partial: int, skipped: int, empty: int} $add
     */
    private function mergeStats(array &$total, array $add): void
    {
        $total['full'] += $add['full'];
        $total['partial'] += $add['partial'];
        $total['skipped'] += $add['skipped'];
        $total['empty'] += $add['empty'];
    }
}
