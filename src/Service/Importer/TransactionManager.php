<?php

namespace BackVista\DatabaseDumps\Service\Importer;

use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;

/**
 * Управление транзакциями БД
 */
class TransactionManager
{
    private DatabaseConnectionInterface $connection;

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Начать транзакцию
     */
    public function begin(): void
    {
        if (!$this->connection->isTransactionActive()) {
            $this->connection->beginTransaction();
        }
    }

    /**
     * Закоммитить транзакцию
     */
    public function commit(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->commit();
        }
    }

    /**
     * Откатить транзакцию
     */
    public function rollBack(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    /**
     * Выполнить код в транзакции с автоматическим rollback при ошибке
     *
     * @template T
     * @param callable(): T $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(callable $callback)
    {
        $this->begin();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
