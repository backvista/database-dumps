<?php

namespace SmartCrm\DatabaseDumps\Service\Importer;

use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;
use SmartCrm\DatabaseDumps\Contract\FileSystemInterface;
use SmartCrm\DatabaseDumps\Contract\LoggerInterface;
use SmartCrm\DatabaseDumps\Service\Parser\SqlParser;

/**
 * Выполнение before/after exec SQL скриптов
 */
class ScriptExecutor
{
    private DatabaseConnectionInterface $connection;
    private FileSystemInterface $fileSystem;
    private SqlParser $parser;
    private LoggerInterface $logger;

    public function __construct(
        DatabaseConnectionInterface $connection,
        FileSystemInterface $fileSystem,
        SqlParser $parser,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->fileSystem = $fileSystem;
        $this->parser = $parser;
        $this->logger = $logger;
    }

    /**
     * Выполнить скрипты из директории
     */
    public function executeScripts(string $directory): void
    {
        if (!$this->fileSystem->isDirectory($directory)) {
            $this->logger->info("Директория не найдена: {$directory}");
            return;
        }

        $files = $this->fileSystem->findFiles($directory, '*.sql');

        if (empty($files)) {
            $this->logger->info("SQL файлы не найдены в: {$directory}");
            return;
        }

        // Сортировка для предсказуемого порядка выполнения
        sort($files);

        foreach ($files as $file) {
            $this->executeScript($file);
        }
    }

    /**
     * Выполнить один SQL скрипт
     */
    private function executeScript(string $filePath): void
    {
        $filename = basename($filePath);
        $this->logger->info("Выполнение: {$filename}");

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
