<?php

namespace BackVista\DatabaseDumps\Config;

/**
 * DTO для конфигурации экспорта таблицы
 */
class TableConfig
{
    private string $schema;
    private string $table;
    private ?int $limit;
    private ?string $where;
    private ?string $orderBy;

    public function __construct(
        string $schema,
        string $table,
        ?int $limit = null,
        ?string $where = null,
        ?string $orderBy = null
    ) {
        $this->schema = $schema;
        $this->table = $table;
        $this->limit = $limit;
        $this->where = $where;
        $this->orderBy = $orderBy;
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getFullTableName(): string
    {
        return "{$this->schema}.{$this->table}";
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getWhere(): ?string
    {
        return $this->where;
    }

    public function getOrderBy(): ?string
    {
        return $this->orderBy;
    }

    public function isFullExport(): bool
    {
        return $this->limit === null;
    }

    public function isPartialExport(): bool
    {
        return $this->limit !== null;
    }

    /**
     * Создать из массива конфигурации
     *
     * @param string $schema
     * @param string $table
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromArray(string $schema, string $table, array $config = []): self
    {
        return new self(
            $schema,
            $table,
            $config['limit'] ?? null,
            $config['where'] ?? null,
            $config['order_by'] ?? null
        );
    }
}
