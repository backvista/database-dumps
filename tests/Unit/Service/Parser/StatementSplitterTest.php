<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Parser;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Service\Parser\StatementSplitter;

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

    public function testSplitWithNewlinesInsideStringLiterals(): void
    {
        $sql = "INSERT INTO changelog (id, description) VALUES (1, 'Необходимо создать новый проект на Java:\ncrypto.vasl-bank-gate\nВерсия Java 21');";

        $statements = $this->splitter->split($sql);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString("Необходимо создать новый проект на Java:\ncrypto.vasl-bank-gate\nВерсия Java 21", $statements[0]);
    }

    public function testSplitWithSemicolonInsideStringLiteral(): void
    {
        $sql = "INSERT INTO t (data) VALUES ('value;with;semicolons'); SELECT 1;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'value;with;semicolons'", $statements[0]);
        $this->assertEquals('SELECT 1', $statements[1]);
    }

    public function testSplitWithDoubleDashInsideStringLiteral(): void
    {
        $sql = "INSERT INTO t (data) VALUES ('some -- not a comment'); SELECT 1;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'some -- not a comment'", $statements[0]);
    }

    public function testSplitWithEscapedQuotes(): void
    {
        $sql = "INSERT INTO t (data) VALUES ('it''s a test'); SELECT 1;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'it''s a test'", $statements[0]);
    }

    public function testSplitWithBackslashEscapedQuotes(): void
    {
        $sql = "INSERT INTO t (data) VALUES ('it\\'s a test'); SELECT 1;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'it\\'s a test'", $statements[0]);
    }

    public function testSplitWithBlockCommentInsideString(): void
    {
        $sql = "INSERT INTO t (data) VALUES ('/* not a comment */'); SELECT 1;";

        $statements = $this->splitter->split($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'/* not a comment */'", $statements[0]);
    }
}
