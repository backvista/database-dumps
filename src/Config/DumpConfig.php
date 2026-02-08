<?php

namespace SmartCrm\DatabaseDumps\Config;

/**
 * DTO для конфигурации экспорта дампов
 */
class DumpConfig
{
    /**
     * @var array<string, array<string>> Полный экспорт по схемам
     */
    private array $fullExport;

    /**
     * @var array<string, array<string, array<string, mixed>>> Частичный экспорт с условиями
     */
    private array $partialExport;

    /**
     * @var array<string> Исключенные таблицы
     */
    private array $exclude;

    /**
     * @param array<string, array<string>> $fullExport Полный экспорт по схемам
     * @param array<string, array<string, array<string, mixed>>> $partialExport Частичный экспорт с условиями
     * @param array<string> $exclude Исключенные таблицы
     */
    public function __construct(
        array $fullExport,
        array $partialExport,
        array $exclude = []
    ) {
        $this->fullExport = $fullExport;
        $this->partialExport = $partialExport;
        $this->exclude = $exclude;
    }

    /**
     * Получить таблицы для полного экспорта из схемы
     *
     * @return array<string>
     */
    public function getFullExportTables(string $schema): array
    {
        return $this->fullExport[$schema] ?? [];
    }

    /**
     * Получить таблицы для частичного экспорта из схемы
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPartialExportTables(string $schema): array
    {
        return $this->partialExport[$schema] ?? [];
    }

    /**
     * Получить все схемы для полного экспорта
     *
     * @return array<string>
     */
    public function getAllFullExportSchemas(): array
    {
        return array_keys($this->fullExport);
    }

    /**
     * Получить все схемы для частичного экспорта
     *
     * @return array<string>
     */
    public function getAllPartialExportSchemas(): array
    {
        return array_keys($this->partialExport);
    }

    /**
     * Проверить, исключена ли таблица
     */
    public function isExcluded(string $table): bool
    {
        return in_array($table, $this->exclude, true);
    }

    /**
     * Получить конфигурацию для конкретной таблицы
     *
     * @return array<string, mixed>|null
     */
    public function getTableConfig(string $schema, string $table): ?array
    {
        // Проверка в partial_export
        if (isset($this->partialExport[$schema][$table])) {
            return $this->partialExport[$schema][$table];
        }

        // Проверка в full_export
        if (isset($this->fullExport[$schema]) && in_array($table, $this->fullExport[$schema], true)) {
            return [];
        }

        return null;
    }
}
