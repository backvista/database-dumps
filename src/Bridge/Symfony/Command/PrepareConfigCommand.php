<?php

namespace BackVista\DatabaseDumps\Bridge\Symfony\Command;

use BackVista\DatabaseDumps\Service\ConfigGenerator\ConfigGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PrepareConfigCommand extends Command
{
    /** @var ConfigGenerator */
    private $generator;

    /** @var string */
    private $projectDir;

    public function __construct(ConfigGenerator $generator, string $projectDir)
    {
        $this->generator = $generator;
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:dbdump:prepare-config')
            ->setDescription('Автоматическая генерация dump_config.yaml на основе структуры БД')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Порог строк для partial_export', '500')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Перезаписать без подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Генерация dump_config.yaml');

        $outputPath = $this->projectDir . '/config/dump_config.yaml';
        /** @var string $thresholdValue */
        $thresholdValue = $input->getOption('threshold');
        $threshold = (int) $thresholdValue;

        if ($threshold <= 0) {
            $io->error('Порог должен быть положительным числом');
            return Command::FAILURE;
        }

        $force = $input->getOption('force');

        if (!$force && file_exists($outputPath)) {
            /** @var bool $confirmed */
            $confirmed = $io->confirm("Файл {$outputPath} уже существует. Перезаписать?", false);
            if (!$confirmed) {
                $io->warning('Отменено');
                return Command::SUCCESS;
            }
        }

        try {
            $io->text("Порог строк: {$threshold}");
            $io->text("Путь: {$outputPath}");
            $io->newLine();

            $stats = $this->generator->generate($outputPath, $threshold);

            $io->success(sprintf(
                "Конфигурация сгенерирована: full=%d, partial=%d, пропущено=%d, пустых=%d",
                $stats['full'],
                $stats['partial'],
                $stats['skipped'],
                $stats['empty']
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка генерации: ' . $e->getMessage());

            if ($io->isVerbose()) {
                $io->note('Трейс: ' . $e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
