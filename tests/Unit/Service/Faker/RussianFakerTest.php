<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Faker;

use BackVista\DatabaseDumps\Service\Faker\PatternDetector;
use BackVista\DatabaseDumps\Service\Faker\RussianFaker;
use PHPUnit\Framework\TestCase;

class RussianFakerTest extends TestCase
{
    /** @var RussianFaker */
    private $faker;

    protected function setUp(): void
    {
        $this->faker = new RussianFaker();
    }

    public function testApplyFio(): void
    {
        $rows = [
            ['id' => 1, 'full_name' => 'Тестов Тест Тестович'],
            ['id' => 2, 'full_name' => 'Другов Друг Другович'],
        ];
        $result = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], $rows);

        $this->assertNotEquals('Тестов Тест Тестович', $result[0]['full_name']);
        $this->assertNotEquals('Другов Друг Другович', $result[1]['full_name']);
        // Should be 3 Cyrillic words
        $this->assertMatchesRegularExpression('/^[А-ЯЁа-яё]+ [А-ЯЁа-яё]+ [А-ЯЁа-яё]+$/u', $result[0]['full_name']);
    }

    public function testApplyFioShort(): void
    {
        $rows = [
            ['id' => 1, 'short_name' => 'Тестов Т.Т.'],
        ];
        $result = $this->faker->apply('public', 'users', ['short_name' => PatternDetector::PATTERN_FIO_SHORT], $rows);

        $this->assertNotEquals('Тестов Т.Т.', $result[0]['short_name']);
        // Format: Фамилия И.О.
        $this->assertMatchesRegularExpression('/^[А-ЯЁа-яё]+ [А-ЯЁ]\.[А-ЯЁ]\.$/u', $result[0]['short_name']);
    }

    public function testApplyEmail(): void
    {
        $rows = [
            ['id' => 1, 'email' => 'original@test.com'],
        ];
        $result = $this->faker->apply('public', 'users', ['email' => PatternDetector::PATTERN_EMAIL], $rows);

        $this->assertNotEquals('original@test.com', $result[0]['email']);
        $this->assertStringContainsString('@', $result[0]['email']);
    }

    public function testApplyPhone(): void
    {
        $rows = [
            ['id' => 1, 'phone' => '+79001234567'],
        ];
        $result = $this->faker->apply('public', 'users', ['phone' => PatternDetector::PATTERN_PHONE], $rows);

        $this->assertNotEquals('+79001234567', $result[0]['phone']);
        $this->assertMatchesRegularExpression('/^79\\d{9}$/', $result[0]['phone']);
    }

    public function testNullPreservation(): void
    {
        $rows = [
            ['id' => 1, 'full_name' => null],
        ];
        $result = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], $rows);

        $this->assertNull($result[0]['full_name']);
    }

    public function testDeterminism(): void
    {
        $rows = [
            ['id' => 1, 'full_name' => 'Оригинал Оригиналов Оригиналович'],
        ];
        $result1 = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], $rows);
        $result2 = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], $rows);

        $this->assertEquals($result1[0]['full_name'], $result2[0]['full_name']);
    }

    public function testEmptyRows(): void
    {
        $result = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], []);
        $this->assertEmpty($result);
    }

    public function testUnchangedColumnsNotInConfig(): void
    {
        $rows = [
            ['id' => 1, 'full_name' => 'Тест Тестов Тестович', 'age' => 25],
        ];
        $result = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], $rows);

        $this->assertEquals(25, $result[0]['age']);
        $this->assertEquals(1, $result[0]['id']);
    }
}
