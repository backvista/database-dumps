<?php

namespace BackVista\DatabaseDumps\Adapter;

use Doctrine\DBAL\Connection;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\PlatformFactory;

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllAssociative(string $sql): array
    {
        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * @param array<mixed> $params
     * @return array<int, mixed>
     */
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

    public function getPlatformName(): string
    {
        $platform = $this->connection->getDatabasePlatform();
        $className = get_class($platform);

        if (strpos($className, 'PostgreSQL') !== false || strpos($className, 'Postgre') !== false) {
            return PlatformFactory::POSTGRESQL;
        }

        if (strpos($className, 'MySQL') !== false || strpos($className, 'MariaDb') !== false) {
            return PlatformFactory::MYSQL;
        }

        return PlatformFactory::POSTGRESQL;
    }
}
