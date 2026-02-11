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

    public function testApplyName(): void
    {
        $rows = [
            ['id' => 1, 'display_name' => 'Тестов Тест'],
        ];
        $result = $this->faker->apply('public', 'users', ['display_name' => PatternDetector::PATTERN_NAME], $rows);

        $this->assertNotEquals('Тестов Тест', $result[0]['display_name']);
        // Should be 2 Cyrillic words
        $this->assertMatchesRegularExpression('/^[А-ЯЁа-яё]+ [А-ЯЁа-яё]+$/u', $result[0]['display_name']);
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

    public function testDeterminismAcrossTables(): void
    {
        $rows = [
            ['id' => 1, 'full_name' => 'Оригинал Оригиналов Оригиналович'],
        ];
        $result1 = $this->faker->apply('public', 'users', ['full_name' => PatternDetector::PATTERN_FIO], $rows);
        $result2 = $this->faker->apply('other_schema', 'employees', ['full_name' => PatternDetector::PATTERN_FIO], $rows);

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

    public function testSameNameDifferentRowsProduceDifferentReplacements(): void
    {
        $fakerConfig = [
            'display_name' => PatternDetector::PATTERN_NAME,
            'email' => PatternDetector::PATTERN_EMAIL,
        ];
        $rows = [
            ['id' => 1, 'display_name' => 'Тестов Тест', 'email' => 'test1@example.com'],
            ['id' => 2, 'display_name' => 'Тестов Тест', 'email' => 'test2@example.com'],
        ];
        $result = $this->faker->apply('public', 'users', $fakerConfig, $rows);

        // Одинаковое имя + разный email → разные замены name
        $this->assertNotEquals($result[0]['display_name'], $result[1]['display_name']);
    }

    public function testFioAndFioShortConsistentInSameRow(): void
    {
        $fakerConfig = [
            'full_name' => PatternDetector::PATTERN_FIO,
            'short_name' => PatternDetector::PATTERN_FIO_SHORT,
        ];
        $rows = [
            ['id' => 1, 'full_name' => 'Тестов Тест Тестович', 'short_name' => 'Тестов Т.Т.'],
        ];
        $result = $this->faker->apply('public', 'users', $fakerConfig, $rows);

        // fio: "Фамилия Имя Отчество", fio_short: "Фамилия И.О."
        $fioParts = explode(' ', $result[0]['full_name']);
        $this->assertCount(3, $fioParts);

        $expectedShort = $fioParts[0] . ' ' . mb_substr($fioParts[1], 0, 1) . '.' . mb_substr($fioParts[2], 0, 1) . '.';
        $this->assertEquals($expectedShort, $result[0]['short_name']);
    }

    public function testEmailCorrespondsToNameInSameRow(): void
    {
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $fakerConfig = [
            'display_name' => PatternDetector::PATTERN_NAME,
            'email' => PatternDetector::PATTERN_EMAIL,
        ];
        $rows = [
            ['id' => 1, 'display_name' => 'Тестов Тест', 'email' => 'test@example.com'],
        ];
        $result = $this->faker->apply('public', 'users', $fakerConfig, $rows);

        // name: "Фамилия Имя", email транслитерирован из того же имени
        $nameParts = explode(' ', $result[0]['display_name']);
        $translitFirst = strtr(mb_strtolower($nameParts[1]), $translitMap);
        $translitLast = strtr(mb_strtolower($nameParts[0]), $translitMap);

        // Email формат: {first}.{last}{num}@{domain}
        $emailLocal = explode('@', $result[0]['email'])[0];
        $this->assertStringStartsWith($translitFirst . '.' . $translitLast, $emailLocal);
    }

    public function testPerRowDeterminismWithMultipleColumns(): void
    {
        $fakerConfig = [
            'full_name' => PatternDetector::PATTERN_FIO,
            'email' => PatternDetector::PATTERN_EMAIL,
            'phone' => PatternDetector::PATTERN_PHONE,
        ];
        $rows = [
            ['id' => 1, 'full_name' => 'Тестов Тест Тестович', 'email' => 'test@example.com', 'phone' => '+79001234567'],
        ];

        $result1 = $this->faker->apply('public', 'users', $fakerConfig, $rows);
        $result2 = $this->faker->apply('public', 'users', $fakerConfig, $rows);

        $this->assertEquals($result1[0]['full_name'], $result2[0]['full_name']);
        $this->assertEquals($result1[0]['email'], $result2[0]['email']);
        $this->assertEquals($result1[0]['phone'], $result2[0]['phone']);
    }
}
