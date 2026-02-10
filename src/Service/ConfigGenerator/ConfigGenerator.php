<?php

namespace BackVista\DatabaseDumps\Service\ConfigGenerator;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\FileSystemInterface;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Service\Faker\PatternDetector;
use BackVista\DatabaseDumps\Service\Graph\TableDependencyResolver;
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

    /** @var TableDependencyResolver */
    private $dependencyResolver;

    /** @var ConfigSplitter */
    private $configSplitter;

    /** @var PatternDetector */
    private $patternDetector;

    /** @var bool */
    private $cascadeEnabled;

    /** @var bool */
    private $fakerEnabled;

    /** @var bool */
    private $splitBySchema;

    public function __construct(
        TableInspector $inspector,
        ServiceTableFilter $filter,
        FileSystemInterface $fileSystem,
        LoggerInterface $logger,
        ConnectionRegistryInterface $registry,
        TableDependencyResolver $dependencyResolver,
        ConfigSplitter $configSplitter,
        PatternDetector $patternDetector,
        bool $cascadeEnabled = true,
        bool $fakerEnabled = true,
        bool $splitBySchema = true
    ) {
        $this->inspector = $inspector;
        $this->filter = $filter;
        $this->fileSystem = $fileSystem;
        $this->logger = $logger;
        $this->registry = $registry;
        $this->dependencyResolver = $dependencyResolver;
        $this->configSplitter = $configSplitter;
        $this->patternDetector = $patternDetector;
        $this->cascadeEnabled = $cascadeEnabled;
        $this->fakerEnabled = $fakerEnabled;
        $this->splitBySchema = $splitBySchema;
    }

    /**
     * @param bool $enabled
     */
    public function setCascadeEnabled(bool $enabled): void
    {
        $this->cascadeEnabled = $enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setFakerEnabled(bool $enabled): void
    {
        $this->fakerEnabled = $enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setSplitBySchema(bool $enabled): void
    {
        $this->splitBySchema = $enabled;
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
        if (!empty($defaultStats[DumpConfig::KEY_FAKER])) {
            $config[DumpConfig::KEY_FAKER] = $defaultStats[DumpConfig::KEY_FAKER];
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
            if (!empty($connStats[DumpConfig::KEY_FAKER])) {
                $connConfig[DumpConfig::KEY_FAKER] = $connStats[DumpConfig::KEY_FAKER];
            }

            if (!empty($connConfig)) {
                $connectionConfigs[$connName] = $connConfig;
            }
        }

        if (!empty($connectionConfigs)) {
            $config[DumpConfig::KEY_CONNECTIONS] = $connectionConfigs;
        }

        if ($this->splitBySchema) {
            $this->configSplitter->split($outputPath, $config);
        } else {
            $yaml = Yaml::dump($config, 4, 2);
            $yaml = $this->addWhereHints($yaml);
            $this->fileSystem->write($outputPath, $yaml);
        }

        return $totalStats;
    }

    /**
     * Сгенерировать конфигурацию для одного подключения
     *
     * @return array{full_export: array<string, array<string>>, partial_export: array<string, array<string, array<string, mixed>>>, faker: array<string, array<string, array<string, string>>>, stats: array{full: int, partial: int, skipped: int, empty: int}}
     */
    private function generateForConnection(?string $connectionName, int $threshold): array
    {
        $stats = ['full' => 0, 'partial' => 0, 'skipped' => 0, 'empty' => 0];

        /** @var array<string, array<string>> $fullExport */
        $fullExport = [];

        /** @var array<string, array<string, array<string, mixed>>> $partialExport */
        $partialExport = [];

        /** @var array<string, array<string, array<string, string>>> $fakerSection */
        $fakerSection = [];

        /** @var array<array{schema: string, table: string}> $nonEmptyTables */
        $nonEmptyTables = [];

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

            $nonEmptyTables[] = ['schema' => $schema, 'table' => $table];

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

        // Обогащение cascade_from из FK графа
        if ($this->cascadeEnabled) {
            $graph = $this->dependencyResolver->getDependencyGraph($connectionName);
            $this->addCascadeFromConfig($partialExport, $fullExport, $graph, $connectionName);
        }

        // Детекция паттернов faker
        if ($this->fakerEnabled) {
            foreach ($nonEmptyTables as $tableInfo) {
                $schema = $tableInfo['schema'];
                $table = $tableInfo['table'];
                $patterns = $this->patternDetector->detect($schema, $table, $connectionName);
                if (!empty($patterns)) {
                    if (!isset($fakerSection[$schema])) {
                        $fakerSection[$schema] = [];
                    }
                    $fakerSection[$schema][$table] = $patterns;
                }
            }
        }

        return [
            DumpConfig::KEY_FULL_EXPORT => $fullExport,
            DumpConfig::KEY_PARTIAL_EXPORT => $partialExport,
            DumpConfig::KEY_FAKER => $fakerSection,
            'stats' => $stats,
        ];
    }

    /**
     * Обогатить partial_export записями cascade_from на основе FK графа.
     *
     * Также проверяет full_export таблицы, имеющие FK-родителей в partial_export,
     * и добавляет им cascade_from (перемещая в partial_export).
     *
     * @param array<string, array<string, array<string, mixed>>> &$partialExport
     * @param array<string, array<string>> &$fullExport
     * @param array<string, array<string, array{source_column: string, target_column: string}>> $graph
     * @param string|null $connectionName
     */
    private function addCascadeFromConfig(array &$partialExport, array &$fullExport, array $graph, ?string $connectionName): void
    {
        // Построить set-ы для быстрого lookup
        /** @var array<string, true> $fullExportSet */
        $fullExportSet = [];
        foreach ($fullExport as $schema => $tables) {
            foreach ($tables as $table) {
                $fullExportSet[$schema . '.' . $table] = true;
            }
        }

        /** @var array<string, true> $partialExportSet */
        $partialExportSet = [];
        foreach ($partialExport as $schema => $tables) {
            foreach ($tables as $table => $conf) {
                $partialExportSet[$schema . '.' . $table] = true;
            }
        }

        // 1. Для каждой partial_export таблицы — добавить cascade_from от partial-родителей
        foreach ($partialExport as $schema => $tables) {
            foreach ($tables as $table => $conf) {
                $childKey = $schema . '.' . $table;
                if (!isset($graph[$childKey])) {
                    continue;
                }

                $cascadeEntries = [];
                foreach ($graph[$childKey] as $parentKey => $columns) {
                    // Пропускаем full_export родителей (все данные присутствуют)
                    if (isset($fullExportSet[$parentKey])) {
                        continue;
                    }
                    // Добавляем только если родитель в partial_export
                    if (isset($partialExportSet[$parentKey])) {
                        $cascadeEntries[] = [
                            'parent' => $parentKey,
                            'fk_column' => $columns['source_column'],
                            'parent_column' => $columns['target_column'],
                        ];
                    }
                }

                if (!empty($cascadeEntries)) {
                    $partialExport[$schema][$table][TableConfig::KEY_CASCADE_FROM] = $cascadeEntries;
                }
            }
        }

        // 2. Проверить full_export таблицы с FK-родителями в partial_export
        foreach ($fullExport as $schema => $tables) {
            foreach ($tables as $index => $table) {
                $childKey = $schema . '.' . $table;
                if (!isset($graph[$childKey])) {
                    continue;
                }

                $cascadeEntries = [];
                foreach ($graph[$childKey] as $parentKey => $columns) {
                    if (isset($partialExportSet[$parentKey])) {
                        $cascadeEntries[] = [
                            'parent' => $parentKey,
                            'fk_column' => $columns['source_column'],
                            'parent_column' => $columns['target_column'],
                        ];
                    }
                }

                if (!empty($cascadeEntries)) {
                    // Переместить из full_export в partial_export с cascade_from
                    unset($fullExport[$schema][$index]);
                    $fullExport[$schema] = array_values($fullExport[$schema]);
                    if (empty($fullExport[$schema])) {
                        unset($fullExport[$schema]);
                    }

                    if (!isset($partialExport[$schema])) {
                        $partialExport[$schema] = [];
                    }
                    $partialExport[$schema][$table] = [
                        TableConfig::KEY_CASCADE_FROM => $cascadeEntries,
                    ];
                }
            }
        }
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
