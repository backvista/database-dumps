<?php

namespace BackVista\DatabaseDumps\Service\Importer;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\FileSystemInterface;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Exception\ImportFailedException;
use BackVista\DatabaseDumps\Service\Parser\SqlParser;
use BackVista\DatabaseDumps\Service\Security\ProductionGuard;

/**
 * Импорт SQL дампов в БД
 */
class DatabaseImporter
{
    public const BEFORE_EXEC_DIR = 'database/before_exec';
    public const AFTER_EXEC_DIR = 'database/after_exec';

    private ConnectionRegistryInterface $registry;
    private DumpConfig $dumpConfig;
    private FileSystemInterface $fileSystem;
    private ProductionGuard $productionGuard;
    private TransactionManager $transactionManager;
    private ScriptExecutor $scriptExecutor;
    private SqlParser $parser;
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(
        ConnectionRegistryInterface $registry,
        DumpConfig $dumpConfig,
        FileSystemInterface $fileSystem,
        ProductionGuard $productionGuard,
        TransactionManager $transactionManager,
        ScriptExecutor $scriptExecutor,
        SqlParser $parser,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->registry = $registry;
        $this->dumpConfig = $dumpConfig;
        $this->fileSystem = $fileSystem;
        $this->productionGuard = $productionGuard;
        $this->transactionManager = $transactionManager;
        $this->scriptExecutor = $scriptExecutor;
        $this->parser = $parser;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Импортировать дампы
     *
     * @param bool $skipBefore Пропустить before_exec скрипты
     * @param bool $skipAfter Пропустить after_exec скрипты
     * @param string|null $schemaFilter Фильтр по схеме
     * @param string|null $connectionFilter Фильтр по подключению (null = дефолтное, 'all' = все)
     * @throws ImportFailedException
     */
    public function import(
        bool $skipBefore = false,
        bool $skipAfter = false,
        ?string $schemaFilter = null,
        ?string $connectionFilter = null
    ): void {
        // 1. Проверка окружения (защита от prod)
        $this->productionGuard->ensureSafeForImport();

        // 2. Определить подключения для импорта
        $connectionNames = $this->resolveConnectionNames($connectionFilter);

        // 3. Per-connection import с транзакциями
        foreach ($connectionNames as $connName) {
            $this->importForConnection($connName, $skipBefore, $skipAfter, $schemaFilter);
        }
    }

    /**
     * Импорт для одного подключения
     */
    private function importForConnection(
        ?string $connectionName,
        bool $skipBefore,
        bool $skipAfter,
        ?string $schemaFilter
    ): void {
        $label = $connectionName ?? 'default';
        $this->logger->info("Импорт подключения: {$label}");

        $this->transactionManager->transaction(function () use ($connectionName, $skipBefore, $skipAfter, $schemaFilter) {
            // Before exec скрипты — на дефолтном подключении
            if (!$skipBefore && $connectionName === null) {
                $this->logger->info('1. Выполнение before_exec скриптов');
                $this->scriptExecutor->executeScripts($this->projectDir . '/' . self::BEFORE_EXEC_DIR);
            }

            // Импорт дампов
            $this->logger->info('2. Импорт SQL дампов');
            $this->importDumps($schemaFilter, $connectionName);

            // After exec скрипты — на дефолтном подключении
            if (!$skipAfter && $connectionName === null) {
                $this->logger->info('3. Выполнение after_exec скриптов');
                $this->scriptExecutor->executeScripts($this->projectDir . '/' . self::AFTER_EXEC_DIR);
            }
        }, $connectionName);
    }

    /**
     * Определить список подключений для импорта
     *
     * @return array<string|null>
     */
    private function resolveConnectionNames(?string $connectionFilter): array
    {
        if ($connectionFilter === ConnectionRegistryInterface::CONNECTION_ALL) {
            $names = [null]; // дефолтное
            foreach (array_keys($this->dumpConfig->getConnectionConfigs()) as $connName) {
                $names[] = $connName;
            }
            return $names;
        }

        if ($connectionFilter !== null) {
            return [$connectionFilter];
        }

        return [null]; // дефолтное
    }

    /**
     * Импортировать дампы из директории
     */
    private function importDumps(?string $schemaFilter, ?string $connectionName): void
    {
        $dumpsPath = $this->buildDumpsPath($connectionName);

        if (!$this->fileSystem->isDirectory($dumpsPath)) {
            throw ImportFailedException::dumpsNotFound($dumpsPath);
        }

        // Поиск всех .sql файлов
        $files = $this->fileSystem->findFiles($dumpsPath, '*.sql');

        if (empty($files)) {
            throw ImportFailedException::noDumpsFound($dumpsPath);
        }

        // Сортировка для предсказуемого порядка
        sort($files);

        $connection = $this->registry->getConnection($connectionName);

        foreach ($files as $file) {
            $this->importDumpFile($file, $schemaFilter, $connection);
        }
    }

    /**
     * Построить путь к директории дампов
     */
    private function buildDumpsPath(?string $connectionName): string
    {
        if ($connectionName !== null) {
            return $this->projectDir . '/' . DumpConfig::DUMPS_DIR . '/' . $connectionName;
        }

        return $this->projectDir . '/' . DumpConfig::DUMPS_DIR;
    }

    /**
     * Импортировать один файл дампа
     *
     * @param \BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface $connection
     */
    private function importDumpFile(string $filePath, ?string $schemaFilter, $connection): void
    {
        // Извлечение schema из пути: database/dumps/{schema}/{table}.sql
        $pathParts = explode(DIRECTORY_SEPARATOR, $filePath);
        $schema = $pathParts[count($pathParts) - 2] ?? '';
        $tableName = basename($filePath, '.sql');

        // Фильтрация по схеме если указано
        if ($schemaFilter && $schema !== $schemaFilter) {
            return;
        }

        $fullName = "{$schema}.{$tableName}";
        $this->logger->info("Импорт: {$fullName}");

        $sql = $this->fileSystem->read($filePath);
        $statements = $this->parser->parseFile($sql);

        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                $connection->executeStatement($statement);
            }
        }

        $this->logger->info("  ✓ Успешно");
    }
}
