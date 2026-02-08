<?php

namespace BackVista\DatabaseDumps\Bridge\Laravel\Command;

use BackVista\DatabaseDumps\Bridge\Laravel\LaravelLogger;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Service\ConfigGenerator\ConfigGenerator;
use Illuminate\Console\Command;

class PrepareConfigCommand extends Command
{
    /** @var string */
    protected $signature = 'dbdump:prepare-config {--threshold=500 : Порог строк для partial_export} {--force : Перезаписать без подтверждения}';

    /** @var string */
    protected $description = 'Автоматическая генерация dump_config.yaml на основе структуры БД';

    /** @var ConfigGenerator */
    private $generator;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $configPath;

    public function __construct(ConfigGenerator $generator, LoggerInterface $logger, string $configPath)
    {
        parent::__construct();
        $this->generator = $generator;
        $this->logger = $logger;
        $this->configPath = $configPath;
    }

    public function handle(): int
    {
        $this->setupLogger();

        $this->info('Генерация dump_config.yaml');

        /** @var string $thresholdValue */
        $thresholdValue = $this->option('threshold');
        $threshold = (int) $thresholdValue;

        if ($threshold <= 0) {
            $this->error('Порог должен быть положительным числом');
            return self::FAILURE;
        }

        $force = $this->option('force');

        if (!$force && file_exists($this->configPath)) {
            /** @var bool $confirmed */
            $confirmed = $this->confirm("Файл {$this->configPath} уже существует. Перезаписать?", false);
            if (!$confirmed) {
                $this->warn('Отменено');
                return self::SUCCESS;
            }
        }

        try {
            $this->line("Порог строк: {$threshold}");
            $this->line("Путь: {$this->configPath}");

            $stats = $this->generator->generate($this->configPath, $threshold);

            $this->info(sprintf(
                "Конфигурация сгенерирована: full=%d, partial=%d, пропущено=%d, пустых=%d",
                $stats['full'],
                $stats['partial'],
                $stats['skipped'],
                $stats['empty']
            ));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка генерации: ' . $e->getMessage());

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
