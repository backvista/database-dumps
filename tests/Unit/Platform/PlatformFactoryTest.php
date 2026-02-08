<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Platform\MySqlPlatform;
use BackVista\DatabaseDumps\Platform\PlatformFactory;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;

class PlatformFactoryTest extends TestCase
{
    public function testCreatePostgresql(): void
    {
        $platform = PlatformFactory::create(PlatformFactory::POSTGRESQL);

        $this->assertInstanceOf(PostgresPlatform::class, $platform);
    }

    public function testCreatePgsql(): void
    {
        $platform = PlatformFactory::create(PlatformFactory::PGSQL);

        $this->assertInstanceOf(PostgresPlatform::class, $platform);
    }

    public function testCreateMysql(): void
    {
        $platform = PlatformFactory::create(PlatformFactory::MYSQL);

        $this->assertInstanceOf(MySqlPlatform::class, $platform);
    }

    public function testCreateMariadb(): void
    {
        $platform = PlatformFactory::create(PlatformFactory::MARIADB);

        $this->assertInstanceOf(MySqlPlatform::class, $platform);
    }

    public function testCreateIsCaseInsensitive(): void
    {
        $this->assertInstanceOf(PostgresPlatform::class, PlatformFactory::create('PostgreSQL'));
        $this->assertInstanceOf(MySqlPlatform::class, PlatformFactory::create('MySQL'));
    }

    public function testCreateThrowsForUnsupportedPlatform(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Неподдерживаемая платформа БД: sqlite');

        PlatformFactory::create('sqlite');
    }
}
