<?php

namespace SmartCrm\DatabaseDumps\Tests\Unit\Service\Parser;

use PHPUnit\Framework\TestCase;
use SmartCrm\DatabaseDumps\Service\Parser\StatementSplitter;

class StatementSplitterTest extends TestCase
{
    private StatementSplitter $splitter;

    protected function setUp(): void
    {
        $this->splitter = new StatementSplitter();
    }

    public function testSplitSimpleStatements(): void
    {
        $sql = "SELECT * FROM users; SELECT * FROM orders;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertEquals("SELECT * FROM users", $statements[0]);
        $this->assertEquals("SELECT * FROM orders", $statements[1]);
    }

    public function testSplitRemovesSingleLineComments(): void
    {
        $sql = "-- This is a comment\nSELECT * FROM users; -- Another comment\nSELECT * FROM orders;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertStringNotContainsString('comment', $statements[0]);
        $this->assertStringNotContainsString('comment', $statements[1]);
    }

    public function testSplitRemovesMultiLineComments(): void
    {
        $sql = "/* This is a\n multiline comment */\nSELECT * FROM users;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(1, $statements);
        $this->assertStringNotContainsString('comment', $statements[0]);
    }

    public function testSplitFiltersEmptyStatements(): void
    {
        $sql = "SELECT * FROM users;;; SELECT * FROM orders;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
    }

    public function testSplitTrimsWhitespace(): void
    {
        $sql = "  SELECT * FROM users  ;   SELECT * FROM orders  ;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertEquals("SELECT * FROM users", $statements[0]);
        $this->assertEquals("SELECT * FROM orders", $statements[1]);
    }

    public function testSplitWithComplexSql(): void
    {
        $sql = <<<SQL
-- Comment 1
TRUNCATE TABLE "users"."users" CASCADE;

/* Multi
   line
   comment */
INSERT INTO "users"."users" (id, name) VALUES
(1, 'User 1'),
(2, 'User 2');

-- Final comment
SELECT setval('users.users_id_seq', 10);
SQL;

        $statements = $this->splitter->split($sql);

        $this->assertCount(3, $statements);
        $this->assertStringContainsString('TRUNCATE', $statements[0]);
        $this->assertStringContainsString('INSERT', $statements[1]);
        $this->assertStringContainsString('setval', $statements[2]);
    }
}
