<?php

namespace SmartCrm\DatabaseDumps\Adapter;

use Doctrine\DBAL\Connection;
use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;

/**
 * Адаптер для Doctrine DBAL Connection
 */
class DoctrineDbalAdapter implements DatabaseConnectionInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function executeStatement(string $sql): void
    {
        $this->connection->executeStatement($sql);
    }

    public function fetchAllAssociative(string $sql): array
    {
        return $this->connection->fetchAllAssociative($sql);
    }

    public function fetchFirstColumn(string $sql, array $params = []): array
    {
        return $this->connection->fetchFirstColumn($sql, $params);
    }

    public function quote($value): string
    {
        return $this->connection->quote($value);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function isTransactionActive(): bool
    {
        return $this->connection->isTransactionActive();
    }
}
