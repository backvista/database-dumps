<?php

namespace BackVista\DatabaseDumps\Service\Generator;

use BackVista\DatabaseDumps\Contract\DatabasePlatformInterface;

/**
 * Генерация TRUNCATE statements
 */
class TruncateGenerator
{
    /** @var DatabasePlatformInterface */
    private $platform;

    public function __construct(DatabasePlatformInterface $platform)
    {
        $this->platform = $platform;
    }

    /**
     * Сгенерировать TRUNCATE statement
     */
    public function generate(string $schema, string $table): string
    {
        return $this->platform->getTruncateStatement($schema, $table);
    }
}
