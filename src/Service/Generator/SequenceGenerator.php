<?php

namespace BackVista\DatabaseDumps\Service\Generator;

use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Contract\DatabasePlatformInterface;

/**
 * Генерация сброса sequences / auto-increment
 */
class SequenceGenerator
{
    /** @var DatabaseConnectionInterface */
    private $connection;

    /** @var DatabasePlatformInterface */
    private $platform;

    public function __construct(DatabaseConnectionInterface $connection, DatabasePlatformInterface $platform)
    {
        $this->connection = $connection;
        $this->platform = $platform;
    }

    /**
     * Сгенерировать statements для сброса sequences / auto-increment
     */
    public function generate(string $schema, string $table): string
    {
        return $this->platform->getSequenceResetSql($schema, $table, $this->connection);
    }
}
