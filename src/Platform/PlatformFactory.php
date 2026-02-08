<?php

namespace BackVista\DatabaseDumps\Platform;

use BackVista\DatabaseDumps\Contract\DatabasePlatformInterface;

/**
 * Фабрика для создания платформы по имени
 */
class PlatformFactory
{
    /**
     * @param string $platformName Имя платформы (postgresql, pgsql, mysql, mariadb)
     * @return DatabasePlatformInterface
     * @throws \InvalidArgumentException
     */
    public static function create(string $platformName): DatabasePlatformInterface
    {
        $normalized = strtolower(trim($platformName));

        switch ($normalized) {
            case 'postgresql':
            case 'pgsql':
                return new PostgresPlatform();
            case 'mysql':
            case 'mariadb':
                return new MySqlPlatform();
            default:
                throw new \InvalidArgumentException("Неподдерживаемая платформа БД: {$platformName}");
        }
    }
}
