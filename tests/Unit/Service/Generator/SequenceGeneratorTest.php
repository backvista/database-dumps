<?php

namespace SmartCrm\DatabaseDumps\Tests\Unit\Service\Generator;

use PHPUnit\Framework\TestCase;
use SmartCrm\DatabaseDumps\Contract\DatabaseConnectionInterface;
use SmartCrm\DatabaseDumps\Service\Generator\SequenceGenerator;

class SequenceGeneratorTest extends TestCase
{
    private DatabaseConnectionInterface $connection;
    private SequenceGenerator $generator;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $this->generator = new SequenceGenerator($this->connection);
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
