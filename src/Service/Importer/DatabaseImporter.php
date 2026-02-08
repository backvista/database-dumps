<?php

namespace SmartCrm\DatabaseDumps\Service\Importer;

use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;
use SmartCrm\DatabaseDumps\Contract\FileSystemInterface;
use SmartCrm\DatabaseDumps\Contract\LoggerInterface;
use SmartCrm\DatabaseDumps\Exception\ImportFailedException;
use SmartCrm\DatabaseDumps\Service\Parser\SqlParser;
use SmartCrm\DatabaseDumps\Service\Security\ProductionGuard;

/**
 * Импорт SQL дампов в БД
 */
class DatabaseImporter
{
    private DatabaseConnectionInterface $connection;
    private FileSystemInterface $fileSystem;
    private ProductionGuard $productionGuard;
    private TransactionManager $transactionManager;
    private ScriptExecutor $scriptExecutor;
    private SqlParser $parser;
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(
        DatabaseConnectionInterface $connection,
        FileSystemInterface $fileSystem,
        ProductionGuard $productionGuard,
        TransactionManager $transactionManager,
        ScriptExecutor $scriptExecutor,
        SqlParser $parser,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->connection = $connection;
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
     * @throws ImportFailedException
     */
    public function import(
        bool $skipBefore = false,
        bool $skipAfter = false,
        ?string $schemaFilter = null
    ): void {
        // 1. Проверка окружения (защита от prod)
        $this->productionGuard->ensureSafeForImport();

        // 2. Импорт в транзакции
        $this->transactionManager->transaction(function () use ($skipBefore, $skipAfter, $schemaFilter) {
            // 3. Before exec скрипты
            if (!$skipBefore) {
                $this->logger->info('1. Выполнение before_exec скриптов');
                $this->scriptExecutor->executeScripts($this->projectDir . '/database/before_exec');
            }

            // 4. Импорт дампов
            $this->logger->info('2. Импорт SQL дампов');
            $this->importDumps($schemaFilter);

            // 5. After exec скрипты
            if (!$skipAfter) {
                $this->logger->info('3. Выполнение after_exec скриптов');
                $this->scriptExecutor->executeScripts($this->projectDir . '/database/after_exec');
            }
        });
    }

    /**
     * Импортировать дампы из директории
     */
    private function importDumps(?string $schemaFilter): void
    {
        $dumpsPath = $this->projectDir . '/database/dumps';

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

        foreach ($files as $file) {
            $this->importDumpFile($file, $schemaFilter);
        }
    }

    /**
     * Импортировать один файл дампа
     */
    private function importDumpFile(string $filePath, ?string $schemaFilter): void
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
                $this->connection->executeStatement($statement);
            }
        }

        $this->logger->info("  ✓ Успешно");
    }
}
