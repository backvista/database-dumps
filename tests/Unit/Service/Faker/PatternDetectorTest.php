<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Faker;

use BackVista\DatabaseDumps\Contract\ConnectionRegistryInterface;
use BackVista\DatabaseDumps\Contract\DatabaseConnectionInterface;
use BackVista\DatabaseDumps\Platform\PostgresPlatform;
use BackVista\DatabaseDumps\Service\Faker\PatternDetector;
use PHPUnit\Framework\TestCase;

class PatternDetectorTest extends TestCase
{
    /** @var DatabaseConnectionInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $connection;
    /** @var PatternDetector */
    private $detector;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(DatabaseConnectionInterface::class);
        $registry = $this->createMock(ConnectionRegistryInterface::class);
        $registry->method('getConnection')->willReturn($this->connection);
        $registry->method('getPlatform')->willReturn(new PostgresPlatform());
        $this->detector = new PatternDetector($registry);
    }

    public function testDetectsEmailPattern(): void
    {
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $rows[] = ['email' => "user{$i}@example.com", 'name' => "Name {$i}"];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertArrayHasKey('email', $result);
        $this->assertEquals(PatternDetector::PATTERN_EMAIL, $result['email']);
    }

    public function testDetectsPhonePattern(): void
    {
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $phone = '79' . str_pad((string)($i * 11111111 % 1000000000), 9, '0', STR_PAD_LEFT);
            $rows[] = ['phone' => '+' . substr($phone, 0, 1) . ' (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7)];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertArrayHasKey('phone', $result);
        $this->assertEquals(PatternDetector::PATTERN_PHONE, $result['phone']);
    }

    public function testDetectsFioPattern(): void
    {
        $rows = [];
        $fios = [
            'Иванов Иван Иванович', 'Петров Пётр Петрович', 'Сидоров Сидор Сидорович',
            'Козлов Андрей Сергеевич', 'Новиков Дмитрий Александрович', 'Морозов Алексей Николаевич',
            'Волков Сергей Владимирович', 'Лебедев Максим Олегович', 'Семёнов Артём Денисович',
            'Егоров Кирилл Игоревич', 'Павлов Роман Андреевич', 'Орлов Даниил Вадимович',
        ];
        foreach ($fios as $fio) {
            $rows[] = ['full_name' => $fio];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertArrayHasKey('full_name', $result);
        $this->assertEquals(PatternDetector::PATTERN_FIO, $result['full_name']);
    }

    public function testDetectsFioShortPattern(): void
    {
        $rows = [];
        $shorts = [
            'Иванов И.И.', 'Петров П.П.', 'Сидоров С.С.', 'Козлов А.С.',
            'Новиков Д.А.', 'Морозов А.Н.', 'Волков С.В.', 'Лебедев М.О.',
            'Семёнов А.Д.', 'Егоров К.И.', 'Павлов Р.А.', 'Орлов Д.В.',
        ];
        foreach ($shorts as $short) {
            $rows[] = ['short_name' => $short];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertArrayHasKey('short_name', $result);
        $this->assertEquals(PatternDetector::PATTERN_FIO_SHORT, $result['short_name']);
    }

    public function testSkipsColumnsWithFewValues(): void
    {
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $rows[] = ['email' => "user{$i}@example.com"];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertEmpty($result);
    }

    public function testBelowThresholdNotDetected(): void
    {
        $rows = [];
        // 15 emails out of 20 = 75% < 80% threshold
        for ($i = 0; $i < 15; $i++) {
            $rows[] = ['email' => "user{$i}@example.com"];
        }
        for ($i = 0; $i < 5; $i++) {
            $rows[] = ['email' => "not-an-email-{$i}"];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertArrayNotHasKey('email', $result);
    }

    public function testDetectsNamePattern(): void
    {
        $rows = [];
        $names = [
            'Иванов Иван', 'Петрова Мария', 'Сидоров Алексей', 'Козлова Елена',
            'Новиков Дмитрий', 'Морозова Ольга', 'Волков Сергей', 'Лебедева Анна',
            'Семёнов Артём', 'Егорова Наталья', 'Павлов Роман', 'Орлова Юлия',
            'Андреев Максим', 'Макарова Ирина', 'Никитин Кирилл', 'Захарова Татьяна',
            'Зайцев Денис', 'Борисова Светлана', 'Яковлев Олег', 'Григорьева Екатерина',
        ];
        foreach ($names as $name) {
            $rows[] = ['display_name' => $name];
        }
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->detector->detect('public', 'users');
        $this->assertArrayHasKey('display_name', $result);
        $this->assertEquals(PatternDetector::PATTERN_NAME, $result['display_name']);
    }

    public function testEmptyTable(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $result = $this->detector->detect('public', 'users');
        $this->assertEmpty($result);
    }
}
