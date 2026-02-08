<?php

namespace BackVista\DatabaseDumps\Platform;

use BackVista\DatabaseDumps\Contract\DatabasePlatformInterface;

/**
 * Фабрика для создания платформы по имени
 */
class PlatformFactory
{
    public const POSTGRESQL = 'postgresql';
    public const PGSQL = 'pgsql';
    public const MYSQL = 'mysql';
    public const MARIADB = 'mariadb';

    /**
     * @param string $platformName Имя платформы (postgresql, pgsql, mysql, mariadb)
     * @return DatabasePlatformInterface
     * @throws \InvalidArgumentException
     */
    public static function create(string $platformName): DatabasePlatformInterface
    {
        $normalized = strtolower(trim($platformName));

        switch ($normalized) {
            case self::POSTGRESQL:
            case self::PGSQL:
                return new PostgresPlatform();
            case self::MYSQL:
            case self::MARIADB:
                return new MySqlPlatform();
            default:
                throw new \InvalidArgumentException("Неподдерживаемая платформа БД: {$platformName}");
        }
    }
}
