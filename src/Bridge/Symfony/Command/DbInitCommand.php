<?php

namespace SmartCrm\DatabaseDumps\Bridge\Symfony\Command;

use SmartCrm\DatabaseDumps\Service\Importer\DatabaseImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbInitCommand extends Command
{
    private DatabaseImporter $importer;

    public function __construct(DatabaseImporter $importer)
    {
        $this->importer = $importer;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:db:init')
            ->setDescription('Инициализация БД с импортом SQL дампов')
            ->addOption('skip-before', null, InputOption::VALUE_NONE, 'Пропустить before_exec скрипты')
            ->addOption('skip-after', null, InputOption::VALUE_NONE, 'Пропустить after_exec скрипты')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Импорт только указанной схемы');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Инициализация БД с импортом дампов');

        $startTime = microtime(true);

        try {
            $this->importer->import(
                $input->getOption('skip-before'),
                $input->getOption('skip-after'),
                $input->getOption('schema')
            );

            $duration = round(microtime(true) - $startTime, 2);
            $io->success("БД успешно инициализирована за {$duration} сек!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка импорта: ' . $e->getMessage());
            $io->warning('Все изменения отменены (rollback)');

            if ($output->isVerbose()) {
                $io->note('Трейс: ' . $e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
