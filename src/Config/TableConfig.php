<?php

namespace BackVista\DatabaseDumps\Config;

/**
 * DTO для конфигурации экспорта таблицы
 */
class TableConfig
{
    public const KEY_LIMIT = 'limit';
    public const KEY_WHERE = 'where';

    public const KEY_ORDER_BY = 'order_by';

    private string $schema;
    private string $table;
    private ?int $limit;
    private ?string $where;
    private ?string $orderBy;
    /** @var string|null */
    private $connectionName;

    public function __construct(
        string $schema,
        string $table,
        ?int $limit = null,
        ?string $where = null,
        ?string $orderBy = null,
        ?string $connectionName = null
    ) {
        $this->schema = $schema;
        $this->table = $table;
        $this->limit = $limit;
        $this->where = $where;
        $this->orderBy = $orderBy;
        $this->connectionName = $connectionName;
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

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * Создать из массива конфигурации
     *
     * @param string $schema
     * @param string $table
     * @param array<string, mixed> $config
     * @param string|null $connectionName
     * @return self
     */
    public static function fromArray(string $schema, string $table, array $config = [], ?string $connectionName = null): self
    {
        return new self(
            $schema,
            $table,
            $config[self::KEY_LIMIT] ?? null,
            $config[self::KEY_WHERE] ?? null,
            $config[self::KEY_ORDER_BY] ?? null,
            $connectionName
        );
    }
}
