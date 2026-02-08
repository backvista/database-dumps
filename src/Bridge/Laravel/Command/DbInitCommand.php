<?php

namespace BackVista\DatabaseDumps\Bridge\Laravel\Command;

use BackVista\DatabaseDumps\Bridge\Laravel\LaravelLogger;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Service\Importer\DatabaseImporter;
use Illuminate\Console\Command;

class DbInitCommand extends Command
{
    /** @var string */
    protected $signature = 'dbdump:import {--skip-before : Пропустить before_exec скрипты} {--skip-after : Пропустить after_exec скрипты} {--schema= : Импорт только указанной схемы} {--connection= : Имя подключения (или "all" для всех)}';

    /** @var string */
    protected $description = 'Инициализация БД с импортом SQL дампов';

    /** @var DatabaseImporter */
    private $importer;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(DatabaseImporter $importer, LoggerInterface $logger)
    {
        parent::__construct();
        $this->importer = $importer;
        $this->logger = $logger;
    }

    public function handle(): int
    {
        $this->setupLogger();

        $this->info('Инициализация БД с импортом дампов');

        $startTime = microtime(true);

        try {
            /** @var string|null $schema */
            $schema = $this->option('schema');
            /** @var string|null $connection */
            $connection = $this->option('connection');
            $this->importer->import(
                (bool) $this->option('skip-before'),
                (bool) $this->option('skip-after'),
                $schema,
                $connection
            );

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("БД успешно инициализирована за {$duration} сек!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка импорта: ' . $e->getMessage());
            $this->warn('Все изменения отменены (rollback)');

            if ($this->getOutput()->isVerbose()) {
                $this->line('Трейс: ' . $e->getTraceAsString());
            }

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
