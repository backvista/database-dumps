<?php

namespace BackVista\DatabaseDumps\Bridge\Laravel;

use BackVista\DatabaseDumps\Adapter\LaravelDatabaseAdapter;
use BackVista\DatabaseDumps\Bridge\Laravel\Command\DbInitCommand;
use BackVista\DatabaseDumps\Bridge\Laravel\Command\DumpExportCommand;
use BackVista\DatabaseDumps\Bridge\Laravel\Command\PrepareConfigCommand;
use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Config\EnvironmentConfig;
use BackVista\DatabaseDumps\Contract\ConfigLoaderInterface;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Contract\DatabasePlatformInterface;
use BackVista\DatabaseDumps\Contract\FakerInterface;
use BackVista\DatabaseDumps\Contract\FileSystemInterface;
use BackVista\DatabaseDumps\Contract\LoggerInterface;
use BackVista\DatabaseDumps\Platform\PlatformFactory;
use BackVista\DatabaseDumps\Service\ConfigGenerator\ConfigGenerator;
use BackVista\DatabaseDumps\Service\ConfigGenerator\ConfigSplitter;
use BackVista\DatabaseDumps\Service\ConfigGenerator\ForeignKeyInspector;
use BackVista\DatabaseDumps\Service\ConfigGenerator\ServiceTableFilter;
use BackVista\DatabaseDumps\Service\ConfigGenerator\TableInspector;
use BackVista\DatabaseDumps\Service\ConnectionRegistry;
use BackVista\DatabaseDumps\Service\Dumper\DatabaseDumper;
use BackVista\DatabaseDumps\Service\Dumper\CascadeWhereResolver;
use BackVista\DatabaseDumps\Service\Dumper\DataFetcher;
use BackVista\DatabaseDumps\Service\Dumper\TableConfigResolver;
use BackVista\DatabaseDumps\Service\Generator\InsertGenerator;
use BackVista\DatabaseDumps\Service\Generator\SequenceGenerator;
use BackVista\DatabaseDumps\Service\Generator\SqlGenerator;
use BackVista\DatabaseDumps\Service\Faker\PatternDetector;
use BackVista\DatabaseDumps\Service\Faker\RussianFaker;
use BackVista\DatabaseDumps\Service\Generator\TruncateGenerator;
use BackVista\DatabaseDumps\Service\Graph\TableDependencyResolver;
use BackVista\DatabaseDumps\Service\Graph\TopologicalSorter;
use BackVista\DatabaseDumps\Service\Importer\DatabaseImporter;
use BackVista\DatabaseDumps\Service\Importer\ScriptExecutor;
use BackVista\DatabaseDumps\Service\Importer\TransactionManager;
use BackVista\DatabaseDumps\Service\Parser\SqlParser;
use BackVista\DatabaseDumps\Service\Parser\StatementSplitter;
use BackVista\DatabaseDumps\Service\Security\EnvironmentChecker;
use BackVista\DatabaseDumps\Service\Security\ProductionGuard;
use BackVista\DatabaseDumps\Util\FileSystemHelper;
use BackVista\DatabaseDumps\Util\YamlConfigLoader;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel ServiceProvider для backvista/database-dumps
 */
class DatabaseDumpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/database-dumps.php', 'database-dumps');

        $this->app->singleton(FileSystemInterface::class, FileSystemHelper::class);
        $this->app->singleton(ConfigLoaderInterface::class, YamlConfigLoader::class);

        $this->app->singleton(LoggerInterface::class, function () {
            return new LaravelLogger();
        });

        $this->app->singleton(EnvironmentConfig::class, function () {
            return EnvironmentConfig::fromEnv();
        });

        $this->app->singleton(DumpConfig::class, function ($app) {
            $configPath = $app['config']->get('database-dumps.config_path');

            if (!file_exists($configPath)) {
                return new DumpConfig([], []);
            }

            /** @var ConfigLoaderInterface $loader */
            $loader = $app->make(ConfigLoaderInterface::class);

            return $loader->load($configPath);
        });

        // Обратная совместимость: singleton connection/platform для внешнего кода
        $this->app->singleton(DatabaseConnectionInterface::class, function ($app) {
            return new LaravelDatabaseAdapter($app['db']->connection());
        });

        $this->app->singleton(DatabasePlatformInterface::class, function ($app) {
            /** @var DatabaseConnectionInterface $connection */
            $connection = $app->make(DatabaseConnectionInterface::class);

            return PlatformFactory::create($connection->getPlatformName());
        });

        // ConnectionRegistry — реестр подключений
        $this->app->singleton(ConnectionRegistryInterface::class, function ($app) {
            $registry = new ConnectionRegistry('default');
            $registry->register('default', new LaravelDatabaseAdapter($app['db']->connection()));

            // Читаем имена подключений из DumpConfig
            /** @var DumpConfig $dumpConfig */
            $dumpConfig = $app->make(DumpConfig::class);
            foreach (array_keys($dumpConfig->getConnectionConfigs()) as $connName) {
                $registry->register($connName, new LaravelDatabaseAdapter(
                    $app['db']->connection($connName)
                ));
            }

            return $registry;
        });

        $this->app->singleton(EnvironmentChecker::class);
        $this->app->singleton(ProductionGuard::class);
        $this->app->singleton(StatementSplitter::class);
        $this->app->singleton(SqlParser::class);
        $this->app->singleton(TransactionManager::class);
        $this->app->singleton(ScriptExecutor::class);
        $this->app->singleton(TableConfigResolver::class);

        $this->app->singleton(TruncateGenerator::class);
        $this->app->singleton(InsertGenerator::class);
        $this->app->singleton(SequenceGenerator::class);
        $this->app->singleton(SqlGenerator::class);

        $this->app->singleton(DataFetcher::class, function ($app) {
            return new DataFetcher(
                $app->make(ConnectionRegistryInterface::class),
                $app->make(CascadeWhereResolver::class),
                $app->make(DumpConfig::class)
            );
        });

        $this->app->singleton(ServiceTableFilter::class);
        $this->app->singleton(TableInspector::class);
        $this->app->singleton(ForeignKeyInspector::class);
        $this->app->singleton(TopologicalSorter::class);
        $this->app->singleton(TableDependencyResolver::class);
        $this->app->singleton(CascadeWhereResolver::class);
        $this->app->singleton(PatternDetector::class);
        $this->app->singleton(FakerInterface::class, RussianFaker::class);
        $this->app->singleton(ConfigSplitter::class);

        $this->app->singleton(ConfigGenerator::class, function ($app) {
            return new ConfigGenerator(
                $app->make(TableInspector::class),
                $app->make(ServiceTableFilter::class),
                $app->make(FileSystemInterface::class),
                $app->make(LoggerInterface::class),
                $app->make(ConnectionRegistryInterface::class),
                $app->make(TableDependencyResolver::class),
                $app->make(ConfigSplitter::class),
                $app->make(PatternDetector::class)
            );
        });

        $this->app->singleton(PrepareConfigCommand::class, function ($app) {
            return new PrepareConfigCommand(
                $app->make(ConfigGenerator::class),
                $app->make(LoggerInterface::class),
                $app['config']->get('database-dumps.config_path')
            );
        });

        $this->app->singleton(DatabaseDumper::class, function ($app) {
            return new DatabaseDumper(
                $app->make(DataFetcher::class),
                $app->make(SqlGenerator::class),
                $app->make(FileSystemInterface::class),
                $app->make(LoggerInterface::class),
                $app['config']->get('database-dumps.project_dir'),
                $app->make(TableDependencyResolver::class),
                $app->make(FakerInterface::class),
                $app->make(DumpConfig::class)
            );
        });

        $this->app->singleton(DatabaseImporter::class, function ($app) {
            return new DatabaseImporter(
                $app->make(ConnectionRegistryInterface::class),
                $app->make(DumpConfig::class),
                $app->make(FileSystemInterface::class),
                $app->make(ProductionGuard::class),
                $app->make(TransactionManager::class),
                $app->make(ScriptExecutor::class),
                $app->make(SqlParser::class),
                $app->make(LoggerInterface::class),
                $app['config']->get('database-dumps.project_dir'),
                $app->make(TableDependencyResolver::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/database-dumps.php' => config_path('database-dumps.php'),
        ], 'database-dumps-config');

        $this->ensureDumpConfigExists();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DumpExportCommand::class,
                DbInitCommand::class,
                PrepareConfigCommand::class,
            ]);
        }
    }

    private function ensureDumpConfigExists(): void
    {
        /** @var string $configPath */
        $configPath = $this->app['config']->get('database-dumps.config_path');

        if (file_exists($configPath)) {
            return;
        }

        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy(__DIR__ . '/stubs/dump_config.yaml', $configPath);
    }
}
