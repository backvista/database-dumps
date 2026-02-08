<?php

namespace SmartCrm\DatabaseDumps\Service\Parser;

/**
 * Парсинг SQL файлов
 */
class SqlParser
{
    private StatementSplitter $splitter;

    public function __construct(StatementSplitter $splitter)
    {
        $this->splitter = $splitter;
    }

    /**
     * Распарсить SQL файл на отдельные statements
     *
     * @return array<string>
     */
    public function parseFile(string $sqlContent): array
    {
        return $this->splitter->split($sqlContent);
    }

    /**
     * Проверить, является ли SQL валидным (базовая проверка)
     */
    public function isValid(string $sql): bool
    {
        $sql = trim($sql);

        if (empty($sql)) {
            return false;
        }

        // Базовая проверка на наличие SQL ключевых слов
        $keywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'CREATE', 'DROP', 'ALTER', 'SET'];

        foreach ($keywords as $keyword) {
            if (stripos($sql, $keyword) === 0) {
                return true;
            }
        }

        return false;
    }
}
