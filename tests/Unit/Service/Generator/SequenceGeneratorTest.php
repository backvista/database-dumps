<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Generator;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;
use BackVista\DatabaseDumps\Service\Generator\SequenceGenerator;

class SequenceGeneratorTest extends TestCase
{
    /** @var DatabaseConnectionInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $connection;

    /** @var SequenceGenerator */
    private $generator;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $platform = new PostgresPlatform();

        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn($platform);

        $this->generator = new SequenceGenerator($registry);
    }

    public function testGenerateWithSequences(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['users.users_id_seq']);

        $sql = $this->generator->generate('users', 'users');

        $this->assertStringContainsString('Сброс sequences', $sql);
        $this->assertStringContainsString("setval('users.users_id_seq'", $sql);
        $this->assertStringContainsString('MAX(id)', $sql);
    }

    public function testGenerateWithNoSequences(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $sql = $this->generator->generate('users', 'users');

        $this->assertStringContainsString('Сброс sequences', $sql);
        $this->assertStringNotContainsString('setval', $sql);
    }

    public function testGenerateHandlesException(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willThrowException(new \Exception('Database error'));

        $sql = $this->generator->generate('users', 'users');

        $this->assertStringContainsString('Ошибка получения sequences', $sql);
        $this->assertStringContainsString('Database error', $sql);
    }
}
