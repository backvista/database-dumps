<?php

namespace SmartCrm\DatabaseDumps\Contract;

/**
 * Интерфейс для работы с подключением к БД
 *
 * Абстракция над конкретными реализациями (Doctrine DBAL, PDO, Laravel DB)
 */
interface DatabaseConnectionInterface
{
    /**
     * Выполнить SQL statement
     */
    public function executeStatement(string $sql): void;

    /**
     * Получить все строки как ассоциативный массив
     */
    public function fetchAllAssociative(string $sql): array;

    /**
     * Получить первую колонку всех строк
     */
    public function fetchFirstColumn(string $sql, array $params = []): array;

    /**
     * Экранировать значение для безопасного использования в SQL
     *
     * @param mixed $value
     */
    public function quote($value): string;

    /**
     * Начать транзакцию
     */
    public function beginTransaction(): void;

    /**
     * Закоммитить транзакцию
     */
    public function commit(): void;

    /**
     * Откатить транзакцию
     */
    public function rollBack(): void;

    /**
     * Проверить, находимся ли в транзакции
     */
    public function isTransactionActive(): bool;
}
