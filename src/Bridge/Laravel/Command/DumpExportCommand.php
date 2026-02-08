<?php

namespace BackVista\DatabaseDumps\Bridge\Laravel\Command;

use BackVista\DatabaseDumps\Bridge\Laravel\LaravelLogger;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Service\Dumper\DatabaseDumper;
use BackVista\DatabaseDumps\Service\Dumper\TableConfigResolver;
use Illuminate\Console\Command;

class DumpExportCommand extends Command
{
    /** @var string */
    protected $signature = 'dump:export {table : Имя таблицы (schema.table) или "all"} {--schema= : Фильтр по схеме для "all"}';

    /** @var string */
    protected $description = 'Экспорт SQL дампа таблицы из БД';

    /** @var DatabaseDumper */
    private $dumper;

    /** @var TableConfigResolver */
    private $configResolver;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(DatabaseDumper $dumper, TableConfigResolver $configResolver, LoggerInterface $logger)
    {
        parent::__construct();
        $this->dumper = $dumper;
        $this->configResolver = $configResolver;
        $this->logger = $logger;
    }

    public function handle(): int
    {
        $this->setupLogger();

        /** @var string $table */
        $table = $this->argument('table');

        if ($table === 'all') {
            return $this->exportAll();
        }

        return $this->exportTable($table);
    }

    private function exportAll(): int
    {
        $this->info('Экспорт всех таблиц согласно конфигурации');

        /** @var string|null $schemaFilter */
        $schemaFilter = $this->option('schema');
        $startTime = microtime(true);

        try {
            $tables = $this->configResolver->resolveAll($schemaFilter);

            if (empty($tables)) {
                $this->warn('Нет таблиц для экспорта в конфигурации');
                return self::FAILURE;
            }

            $this->dumper->exportAll($tables);

            $duration = round(microtime(true) - $startTime, 2);
            $totalTables = count($tables);
            $this->info("Экспортировано таблиц: {$totalTables} за {$duration} сек");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка экспорта: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line('Трейс: ' . $e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function exportTable(string $fullTableName): int
    {
        if (strpos($fullTableName, '.') === false) {
            $this->error('Неверный формат таблицы. Используйте формат: schema.table');
            return self::FAILURE;
        }

        /** @var array{0: string, 1: string} $parts */
        $parts = explode('.', $fullTableName, 2);
        $schema = $parts[0];
        $table = $parts[1];

        $this->line("Экспорт: {$fullTableName}");

        try {
            $config = $this->configResolver->resolve($schema, $table);
            $this->dumper->exportTable($config);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function setupLogger(): void
    {
        if ($this->logger instanceof LaravelLogger) {
            $command = $this;
            $this->logger = new LaravelLogger(function ($message) use ($command) {
                $command->line($message);
            });
            $this->getLaravel()->instance(LoggerInterface::class, $this->logger);
        }
    }
}
