<?php

namespace BackVista\DatabaseDumps\Service\Generator;

use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;

/**
 * Генерация TRUNCATE statements
 */
class TruncateGenerator
{
    /** @var ConnectionRegistryInterface */
    private $registry;

    public function __construct(ConnectionRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Сгенерировать TRUNCATE statement
     */
    public function generate(string $schema, string $table, ?string $connectionName = null): string
    {
        $platform = $this->registry->getPlatform($connectionName);

        return $platform->getTruncateStatement($schema, $table);
    }
}
