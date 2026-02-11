<?php

namespace BackVista\DatabaseDumps\Service\Faker;

use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;

/**
 * Определяет паттерны персональных данных в колонках таблицы
 * по выборке случайных строк (порог совпадения 80%).
 */
class PatternDetector
{
    /** @var string Фамилия Имя Отчество (3 кириллических слова) */
    public const PATTERN_FIO = 'fio';
    /** @var string Фамилия И.О. (сокращённое ФИО с инициалами) */
    public const PATTERN_FIO_SHORT = 'fio_short';
    /** @var string Фамилия Имя (2 кириллических слова) */
    public const PATTERN_NAME = 'name';
    /** @var string Email-адрес */
    public const PATTERN_EMAIL = 'email';
    /** @var string Телефонный номер */
    public const PATTERN_PHONE = 'phone';

    /** @var int Размер выборки для анализа */
    public const SAMPLE_SIZE = 200;
    /** @var float Минимальная доля совпадений для детекции (80%) */
    public const DETECTION_THRESHOLD = 0.80;

    /** @var string */
    private const REGEX_EMAIL = '/^[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}$/u';
    /** @var string */
    private const REGEX_PHONE = '/^(?:\\+?[78])?[9]\\d{9}$/';
    /** @var string 3 кириллических слова (с поддержкой дефиса) */
    private const REGEX_FIO = '/^[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?$/u';
    /** @var string Фамилия + 2 инициала с точками */
    private const REGEX_FIO_SHORT = '/^[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁ]\\.\\s?[А-ЯЁ]\\.$/u';
    /** @var string 2 кириллических слова (с поддержкой дефиса) */
    private const REGEX_NAME = '/^[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?$/u';

    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(ConnectionRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Анализирует колонки таблицы и возвращает обнаруженные паттерны ПД.
     *
     * @return array<string, string> column_name => pattern_type
     */
    public function detect(string $schema, string $table, ?string $connectionName = null): array
    {
        $connection = $this->registry->getConnection($connectionName);
        $platform = $this->registry->getPlatform($connectionName);

        $fullTable = $platform->getFullTableName($schema, $table);
        $randomFunc = $platform->getRandomFunctionSql();
        $sql = "SELECT * FROM {$fullTable} ORDER BY {$randomFunc} LIMIT " . self::SAMPLE_SIZE;

        $rows = $connection->fetchAllAssociative($sql);

        if (empty($rows)) {
            return [];
        }

        $detected = [];
        $columns = array_keys($rows[0]);

        foreach ($columns as $column) {
            $values = [];
            foreach ($rows as $row) {
                if ($row[$column] !== null && $row[$column] !== '') {
                    $values[] = (string) $row[$column];
                }
            }

            if (count($values) < 10) {
                continue;
            }

            $pattern = $this->detectColumnPattern($values);
            if ($pattern !== null) {
                $detected[$column] = $pattern;
            }
        }

        return $detected;
    }

    /**
     * Определяет паттерн ПД по массиву значений колонки.
     * Возвращает первый паттерн, превысивший порог совпадения.
     *
     * @param array<string> $values непустые значения колонки
     */
    private function detectColumnPattern(array $values): ?string
    {
        $total = count($values);
        $patterns = [
            self::PATTERN_EMAIL => self::REGEX_EMAIL,
            self::PATTERN_PHONE => null,  // special handling
            self::PATTERN_FIO => self::REGEX_FIO,
            self::PATTERN_FIO_SHORT => self::REGEX_FIO_SHORT,
            self::PATTERN_NAME => self::REGEX_NAME,
        ];

        foreach ($patterns as $patternName => $regex) {
            $matches = 0;

            foreach ($values as $value) {
                if ($patternName === self::PATTERN_PHONE) {
                    // Strip non-digits before matching
                    $cleaned = preg_replace('/[^\\d]/', '', $value);
                    if ($cleaned !== null && preg_match(self::REGEX_PHONE, $cleaned)) {
                        $matches++;
                    }
                } else {
                    $trimmed = trim($value);
                    if ($regex !== null && preg_match($regex, $trimmed)) {
                        $matches++;
                    }
                }
            }

            if ($total > 0 && ($matches / $total) >= self::DETECTION_THRESHOLD) {
                return $patternName;
            }
        }

        return null;
    }
}
