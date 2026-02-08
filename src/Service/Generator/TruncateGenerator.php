<?php

namespace SmartCrm\DatabaseDumps\Service\Generator;

/**
 * Генерация TRUNCATE statements
 */
class TruncateGenerator
{
    /**
     * Сгенерировать TRUNCATE CASCADE statement
     */
    public function generate(string $schema, string $table): string
    {
        $fullTable = $this->quoteIdentifier($schema, $table);

        return "TRUNCATE TABLE {$fullTable} CASCADE;";
    }

    /**
     * Экранировать идентификатор (схема.таблица)
     */
    private function quoteIdentifier(string $schema, string $table): string
    {
        return "\"{$schema}\".\"{$table}\"";
    }
}
