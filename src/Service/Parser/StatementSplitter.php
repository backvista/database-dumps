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
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $char = $sql[$i];

            // Однострочный комментарий: --
            if ($char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end + 1;
                continue;
            }

            // Многострочный комментарий: /* ... */
            if ($char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                continue;
            }

            // Строковый литерал (одинарные кавычки)
            if ($char === '\'') {
                $current .= $char;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    $current .= $c;
                    if ($c === '\\' && $i + 1 < $len) {
                        // Backslash-escape (MySQL style)
                        $i++;
                        $current .= $sql[$i];
                        $i++;
                        continue;
                    }
                    if ($c === '\'' && $i + 1 < $len && $sql[$i + 1] === '\'') {
                        // Escaped quote '' (SQL standard)
                        $i++;
                        $current .= $sql[$i];
                        $i++;
                        continue;
                    }
                    if ($c === '\'') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // Идентификатор в двойных кавычках
            if ($char === '"') {
                $current .= $char;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    $current .= $c;
                    if ($c === '"' && $i + 1 < $len && $sql[$i + 1] === '"') {
                        $i++;
                        $current .= $sql[$i];
                        $i++;
                        continue;
                    }
                    if ($c === '"') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // Разделитель statements
            if ($char === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
