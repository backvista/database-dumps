<?php

namespace BackVista\DatabaseDumps\Service\Faker;

use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;

class PatternDetector
{
    public const PATTERN_FIO = 'fio';
    public const PATTERN_FIO_SHORT = 'fio_short';
    public const PATTERN_EMAIL = 'email';
    public const PATTERN_PHONE = 'phone';

    public const SAMPLE_SIZE = 200;
    public const DETECTION_THRESHOLD = 0.80;

    /** @var string */
    private const REGEX_EMAIL = '/^[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}$/u';
    /** @var string */
    private const REGEX_PHONE = '/^(?:\\+?[78])?[9]\\d{9}$/';
    /** @var string */
    private const REGEX_FIO = '/^[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?$/u';
    /** @var string */
    private const REGEX_FIO_SHORT = '/^[А-ЯЁа-яё]+(?:\\-[А-ЯЁа-яё]+)?\\s+[А-ЯЁ]\\.\\s?[А-ЯЁ]\\.$/u';

    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(ConnectionRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Detect sensitive data patterns in table columns.
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
     * @param array<string> $values non-null column values
     */
    private function detectColumnPattern(array $values): ?string
    {
        $total = count($values);
        $patterns = [
            self::PATTERN_EMAIL => self::REGEX_EMAIL,
            self::PATTERN_PHONE => null,  // special handling
            self::PATTERN_FIO => self::REGEX_FIO,
            self::PATTERN_FIO_SHORT => self::REGEX_FIO_SHORT,
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
