<?php

namespace BackVista\DatabaseDumps\Service\Dumper;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\TableConfig;
use BackVista\DatabaseDumps\Contract\FileSystemInterface;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Exception\ExportFailedException;
use BackVista\DatabaseDumps\Service\Generator\SqlGenerator;

/**
 * Экспорт данных из БД в SQL дампы
 */
class DatabaseDumper
{
    private DataFetcher $dataFetcher;
    private SqlGenerator $sqlGenerator;
    private FileSystemInterface $fileSystem;
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(
        DataFetcher $dataFetcher,
        SqlGenerator $sqlGenerator,
        FileSystemInterface $fileSystem,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->dataFetcher = $dataFetcher;
        $this->sqlGenerator = $sqlGenerator;
        $this->fileSystem = $fileSystem;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Экспортировать таблицу
     */
    public function exportTable(TableConfig $config): void
    {
        $this->doExportTable($config, null, null);
    }

    /**
     * Экспортировать все таблицы
     *
     * @param array<TableConfig> $tables
     */
    public function exportAll(array $tables): void
    {
        $total = count($tables);
        $current = 0;

        foreach ($tables as $config) {
            $current++;
            $this->doExportTable($config, $current, $total);
        }
    }

    /**
     * @param int|null $current Номер текущей таблицы (null = одиночный экспорт)
     * @param int|null $total Общее количество таблиц
     */
    private function doExportTable(TableConfig $config, ?int $current, ?int $total): void
    {
        $prefix = ($current !== null && $total !== null)
            ? "[{$current}/{$total}] "
            : '';
        $tableName = $config->getFullTableName();

        try {
            // 1. Загрузка данных
            $rows = $this->dataFetcher->fetch($config);

            // 2. Генерация SQL
            $sql = $this->sqlGenerator->generate($config, $rows);

            // 3. Сохранение файла
            $filename = $this->buildDumpPath($config);
            $this->ensureDirectoryExists(dirname($filename));
            $this->fileSystem->write($filename, $sql);

            $size = $this->fileSystem->getFileSize($filename);
            $this->logger->info("{$prefix}{$tableName} ... OK ({$this->formatBytes($size)})");
        } catch (\Exception $e) {
            $this->logger->error("{$prefix}{$tableName} ... ERROR: " . $e->getMessage());
            throw ExportFailedException::fromException($tableName, $e);
        }
    }

    /**
     * Построить путь к dump-файлу
     *
     * Дефолтное подключение: database/dumps/{schema}/{table}.sql
     * Именованное подключение: database/dumps/{connection}/{schema}/{table}.sql
     */
    private function buildDumpPath(TableConfig $config): string
    {
        $connectionName = $config->getConnectionName();

        $dumpsDir = DumpConfig::DUMPS_DIR;

        if ($connectionName !== null) {
            return $this->projectDir . "/{$dumpsDir}/{$connectionName}/{$config->getSchema()}/{$config->getTable()}.sql";
        }

        return $this->projectDir . "/{$dumpsDir}/{$config->getSchema()}/{$config->getTable()}.sql";
    }

    /**
     * Убедиться что директория существует
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!$this->fileSystem->exists($directory)) {
            $this->fileSystem->createDirectory($directory);
        }
    }

    /**
     * Форматировать байты в читаемый формат
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
