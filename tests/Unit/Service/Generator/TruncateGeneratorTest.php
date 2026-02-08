<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Generator;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;
use BackVista\DatabaseDumps\Service\Generator\TruncateGenerator;

class TruncateGeneratorTest extends TestCase
{
    /** @var TruncateGenerator */
    private $generator;

    protected function setUp(): void
    {
        $platform = new PostgresPlatform();
        $this->generator = new TruncateGenerator($platform);
    }

    public function testGenerate(): void
    {
        $sql = $this->generator->generate('users', 'users');

        $this->assertEquals('TRUNCATE TABLE "users"."users" CASCADE;', $sql);
    }

    public function testGenerateWithDifferentSchema(): void
    {
        $sql = $this->generator->generate('clients', 'clients');

        $this->assertEquals('TRUNCATE TABLE "clients"."clients" CASCADE;', $sql);
    }

    public function testGenerateQuotesIdentifiers(): void
    {
        $sql = $this->generator->generate('test_schema', 'test_table');

        $this->assertStringContainsString('"test_schema"', $sql);
        $this->assertStringContainsString('"test_table"', $sql);
    }

    public function testGenerateIncludesCascade(): void
    {
        $sql = $this->generator->generate('users', 'users');

        $this->assertStringContainsString('CASCADE', $sql);
    }
}
