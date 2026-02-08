<?php

namespace BackVista\DatabaseDumps\Service\Parser;

/**
 * Разбивает SQL файл на отдельные statements
 */
class StatementSplitter
{
    /**
     * Разбить SQL на отдельные команды
     *
     * @return array<string>
     */
    public function split(string $sql): array
    {
        // Удаление однострочных комментариев (-- комментарий)
        $sql = (string) preg_replace('/--.*$/m', '', $sql);

        // Удаление многострочных комментариев (/* комментарий */)
        $sql = (string) preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Разбивка по точке с запятой
        $statements = explode(';', $sql);

        // Фильтрация пустых statements
        return array_values(array_filter(
            array_map('trim', $statements),
            function ($statement) {
                return !empty($statement);
            }
        ));
    }
}
