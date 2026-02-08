<?php

namespace SmartCrm\DatabaseDumps\Service\Dumper;

use SmartCrm\DatabaseDumps\Config\DumpConfig;
use SmartCrm\DatabaseDumps\Config\TableConfig;

/**
 * Получение конфигурации таблицы для экспорта
 */
class TableConfigResolver
{
    private DumpConfig $dumpConfig;

    public function __construct(DumpConfig $dumpConfig)
    {
        $this->dumpConfig = $dumpConfig;
    }

    /**
     * Получить конфигурацию для таблицы
     */
    public function resolve(string $schema, string $table): TableConfig
    {
        $config = $this->dumpConfig->getTableConfig($schema, $table);

        return TableConfig::fromArray($schema, $table, $config ?? []);
    }

    /**
     * Получить все таблицы для экспорта из схемы
     *
     * @return array<TableConfig>
     */
    public function resolveAllFromSchema(string $schema): array
    {
        $tables = [];

        // Full export таблицы
        foreach ($this->dumpConfig->getFullExportTables($schema) as $table) {
            $tables[] = $this->resolve($schema, $table);
        }

        // Partial export таблицы
        foreach ($this->dumpConfig->getPartialExportTables($schema) as $table => $config) {
            $tables[] = TableConfig::fromArray($schema, $table, $config);
        }

        return $tables;
    }

    /**
     * Получить все таблицы для экспорта (все схемы)
     *
     * @return array<TableConfig>
     */
    public function resolveAll(?string $schemaFilter = null): array
    {
        $tables = [];

        // Full export
        foreach ($this->dumpConfig->getAllFullExportSchemas() as $schema) {
            if ($schemaFilter && $schema !== $schemaFilter) {
                continue;
            }

            $tables = array_merge($tables, $this->resolveAllFromSchema($schema));
        }

        // Partial export
        foreach ($this->dumpConfig->getAllPartialExportSchemas() as $schema) {
            if ($schemaFilter && $schema !== $schemaFilter) {
                continue;
            }

            foreach ($this->dumpConfig->getPartialExportTables($schema) as $table => $config) {
                // Пропускаем если уже добавлено в full export
                $exists = false;
                foreach ($tables as $existingTable) {
                    if ($existingTable->getSchema() === $schema && $existingTable->getTable() === $table) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $tables[] = TableConfig::fromArray($schema, $table, $config);
                }
            }
        }

        return $tables;
    }
}
