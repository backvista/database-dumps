<?php

namespace BackVista\DatabaseDumps\Service\Faker;

use BackVista\DatabaseDumps\Contract\FakerInterface;

/**
 * Заменяет персональные данные на сгенерированные русские ФИО, email, телефоны.
 * Детерминирован: seed по хешу комбинации всех faker-значений строки.
 */
class RussianFaker implements FakerInterface
{
    /** @var array<string> */
    private const LAST_NAMES_MALE = [
        'Иванов', 'Петров', 'Сидоров', 'Козлов', 'Новиков',
        'Морозов', 'Волков', 'Лебедев', 'Семёнов', 'Егоров',
        'Павлов', 'Козлов', 'Степанов', 'Николаев', 'Орлов',
        'Андреев', 'Макаров', 'Никитин', 'Захаров', 'Зайцев',
        'Соловьёв', 'Борисов', 'Яковлев', 'Григорьев', 'Романов',
        'Воробьёв', 'Сергеев', 'Кузнецов', 'Фролов', 'Александров',
        'Дмитриев', 'Королёв', 'Гусев', 'Киселёв', 'Ильин',
        'Максимов', 'Поляков', 'Сорокин', 'Виноградов', 'Ковалёв',
        'Белов', 'Медведев', 'Антонов', 'Тарасов', 'Жуков',
        'Баранов', 'Филиппов', 'Комаров', 'Давыдов', 'Беляев',
        'Герасимов', 'Богданов', 'Осипов', 'Сидоров', 'Матвеев',
        'Титов', 'Марков', 'Миронов', 'Крылов', 'Куликов',
        'Карпов', 'Власов', 'Мельников', 'Денисов', 'Гаврилов',
        'Тихонов', 'Казаков', 'Афанасьев', 'Данилов', 'Пономарёв',
        'Калинин', 'Кириллов', 'Клименко', 'Ефимов', 'Лазарев',
        'Суворов', 'Чернов', 'Рябов', 'Поликарпов', 'Субботин',
        'Шилов', 'Устинов', 'Большаков', 'Савин', 'Панов',
        'Рыбаков', 'Суханов', 'Широков', 'Кудрявцев', 'Прохоров',
        'Наумов', 'Потапов', 'Журавлёв', 'Овчинников', 'Трофимов',
        'Леонов', 'Соболев', 'Ермаков', 'Колесников', 'Гончаров',
    ];

    /** @var array<string> */
    private const LAST_NAMES_FEMALE = [
        'Иванова', 'Петрова', 'Сидорова', 'Козлова', 'Новикова',
        'Морозова', 'Волкова', 'Лебедева', 'Семёнова', 'Егорова',
        'Павлова', 'Козлова', 'Степанова', 'Николаева', 'Орлова',
        'Андреева', 'Макарова', 'Никитина', 'Захарова', 'Зайцева',
        'Соловьёва', 'Борисова', 'Яковлева', 'Григорьева', 'Романова',
        'Воробьёва', 'Сергеева', 'Кузнецова', 'Фролова', 'Александрова',
        'Дмитриева', 'Королёва', 'Гусева', 'Киселёва', 'Ильина',
        'Максимова', 'Полякова', 'Сорокина', 'Виноградова', 'Ковалёва',
        'Белова', 'Медведева', 'Антонова', 'Тарасова', 'Жукова',
        'Баранова', 'Филиппова', 'Комарова', 'Давыдова', 'Беляева',
        'Герасимова', 'Богданова', 'Осипова', 'Сидорова', 'Матвеева',
        'Титова', 'Маркова', 'Миронова', 'Крылова', 'Куликова',
        'Карпова', 'Власова', 'Мельникова', 'Денисова', 'Гаврилова',
        'Тихонова', 'Казакова', 'Афанасьева', 'Данилова', 'Пономарёва',
        'Калинина', 'Кириллова', 'Клименко', 'Ефимова', 'Лазарева',
        'Суворова', 'Чернова', 'Рябова', 'Поликарпова', 'Субботина',
        'Шилова', 'Устинова', 'Большакова', 'Савина', 'Панова',
        'Рыбакова', 'Суханова', 'Широкова', 'Кудрявцева', 'Прохорова',
        'Наумова', 'Потапова', 'Журавлёва', 'Овчинникова', 'Трофимова',
        'Леонова', 'Соболева', 'Ермакова', 'Колесникова', 'Гончарова',
    ];

    /** @var array<string> */
    private const FIRST_NAMES_MALE = [
        'Иван', 'Пётр', 'Александр', 'Дмитрий', 'Сергей',
        'Андрей', 'Алексей', 'Максим', 'Михаил', 'Николай',
        'Владимир', 'Евгений', 'Виктор', 'Олег', 'Артём',
        'Роман', 'Даниил', 'Кирилл', 'Денис', 'Игорь',
        'Антон', 'Вадим', 'Юрий', 'Павел', 'Василий',
        'Борис', 'Григорий', 'Тимур', 'Руслан', 'Константин',
        'Фёдор', 'Степан', 'Геннадий', 'Леонид', 'Валерий',
        'Анатолий', 'Виталий', 'Аркадий', 'Семён', 'Марк',
        'Глеб', 'Тимофей', 'Матвей', 'Лев', 'Егор',
        'Ярослав', 'Станислав', 'Вячеслав', 'Филипп', 'Эдуард',
        'Георгий', 'Владислав', 'Захар', 'Богдан', 'Арсений',
        'Илья', 'Никита', 'Савелий', 'Платон', 'Макар',
        'Демид', 'Прохор', 'Мирон', 'Назар', 'Елисей',
        'Вениамин', 'Всеволод', 'Герман', 'Давид', 'Добрыня',
        'Емельян', 'Ефим', 'Лука', 'Потап', 'Радомир',
        'Святослав', 'Тихон', 'Трофим', 'Харитон', 'Ростислав',
    ];

    /** @var array<string> */
    private const FIRST_NAMES_FEMALE = [
        'Анна', 'Мария', 'Елена', 'Ольга', 'Наталья',
        'Ирина', 'Татьяна', 'Светлана', 'Екатерина', 'Юлия',
        'Марина', 'Валентина', 'Галина', 'Людмила', 'Надежда',
        'Вера', 'Любовь', 'Алина', 'Дарья', 'Виктория',
        'Полина', 'Софья', 'Ксения', 'Кристина', 'Диана',
        'Алёна', 'Оксана', 'Жанна', 'Лариса', 'Тамара',
        'Нина', 'Инна', 'Раиса', 'Зинаида', 'Клавдия',
        'Лидия', 'Антонина', 'Маргарита', 'Евгения', 'Валерия',
        'Милана', 'Варвара', 'Василиса', 'Ева', 'Агата',
        'Злата', 'Вероника', 'Камилла', 'Арина', 'Ульяна',
        'Мирослава', 'Яна', 'Регина', 'Элина', 'Ангелина',
        'Таисия', 'Стефания', 'Серафима', 'Майя', 'Эмилия',
        'Каролина', 'Амелия', 'Аделина', 'Снежана', 'Влада',
        'Альбина', 'Пелагея', 'Лилия', 'Марта', 'Нелли',
        'Римма', 'Роза', 'Эльвира', 'Фаина', 'Аза',
        'Берта', 'Виолетта', 'Изабелла', 'Клара', 'Флора',
    ];

    /** @var array<string> */
    private const PATRONYMICS_MALE = [
        'Иванович', 'Петрович', 'Александрович', 'Дмитриевич', 'Сергеевич',
        'Андреевич', 'Алексеевич', 'Максимович', 'Михайлович', 'Николаевич',
        'Владимирович', 'Евгеньевич', 'Викторович', 'Олегович', 'Артёмович',
        'Романович', 'Даниилович', 'Кириллович', 'Денисович', 'Игоревич',
        'Антонович', 'Вадимович', 'Юрьевич', 'Павлович', 'Васильевич',
        'Борисович', 'Григорьевич', 'Тимурович', 'Русланович', 'Константинович',
        'Фёдорович', 'Степанович', 'Геннадьевич', 'Леонидович', 'Валерьевич',
        'Анатольевич', 'Витальевич', 'Аркадьевич', 'Семёнович', 'Маркович',
        'Глебович', 'Тимофеевич', 'Матвеевич', 'Львович', 'Егорович',
        'Ярославович', 'Станиславович', 'Вячеславович', 'Филиппович', 'Эдуардович',
    ];

    /** @var array<string> */
    private const PATRONYMICS_FEMALE = [
        'Ивановна', 'Петровна', 'Александровна', 'Дмитриевна', 'Сергеевна',
        'Андреевна', 'Алексеевна', 'Максимовна', 'Михайловна', 'Николаевна',
        'Владимировна', 'Евгеньевна', 'Викторовна', 'Олеговна', 'Артёмовна',
        'Романовна', 'Данииловна', 'Кирилловна', 'Денисовна', 'Игоревна',
        'Антоновна', 'Вадимовна', 'Юрьевна', 'Павловна', 'Васильевна',
        'Борисовна', 'Григорьевна', 'Тимуровна', 'Руслановна', 'Константиновна',
        'Фёдоровна', 'Степановна', 'Геннадьевна', 'Леонидовна', 'Валерьевна',
        'Анатольевна', 'Витальевна', 'Аркадьевна', 'Семёновна', 'Марковна',
        'Глебовна', 'Тимофеевна', 'Матвеевна', 'Львовна', 'Егоровна',
        'Ярославовна', 'Станиславовна', 'Вячеславовна', 'Филипповна', 'Эдуардовна',
    ];

    /** @var array<string> */
    private const EMAIL_DOMAINS = ['example.com', 'test.ru', 'mail.test', 'demo.org'];

    /** @var array<string, array<string>> Карта форматов гендера: lowercase → [male_variant, female_variant] */
    private const GENDER_MAP = [
        'male' => ['male', 'female'],
        'female' => ['male', 'female'],
        'm' => ['m', 'f'],
        'f' => ['m', 'f'],
        'м' => ['м', 'ж'],
        'ж' => ['м', 'ж'],
        'мужской' => ['мужской', 'женский'],
        'женский' => ['мужской', 'женский'],
        'муж' => ['муж', 'жен'],
        'жен' => ['муж', 'жен'],
        'мужчина' => ['мужчина', 'женщина'],
        'женщина' => ['мужчина', 'женщина'],
    ];

    /** @var array<string, string> */
    private const TRANSLIT_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /**
     * Заменяет ПД в строках данных согласно fakerConfig.
     * Seed привязан к комбинации всех faker-значений строки, а не к отдельной ячейке.
     *
     * @inheritDoc
     */
    public function apply(string $schema, string $table, array $fakerConfig, array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            // Комбинированный seed от всех faker-значений строки
            $seedParts = [];
            foreach ($fakerConfig as $column => $patternType) {
                $seedParts[] = isset($row[$column]) ? (string) $row[$column] : '';
            }
            mt_srand(crc32(implode("\0", $seedParts)));

            // Один «человек» на строку
            $gender = mt_rand(0, 1); // 0=male, 1=female

            $lastNameList = $gender ? self::LAST_NAMES_FEMALE : self::LAST_NAMES_MALE;
            $firstNameList = $gender ? self::FIRST_NAMES_FEMALE : self::FIRST_NAMES_MALE;
            $patronymicList = $gender ? self::PATRONYMICS_FEMALE : self::PATRONYMICS_MALE;

            $lastName = $lastNameList[mt_rand(0, count($lastNameList) - 1)];
            $firstName = $firstNameList[mt_rand(0, count($firstNameList) - 1)];
            $patronymic = $patronymicList[mt_rand(0, count($patronymicList) - 1)];

            foreach ($fakerConfig as $column => $patternType) {
                if (!isset($row[$column])) {
                    continue;
                }

                switch ($patternType) {
                    case PatternDetector::PATTERN_FIO:
                        $row[$column] = $lastName . ' ' . $firstName . ' ' . $patronymic;
                        break;
                    case PatternDetector::PATTERN_FIO_SHORT:
                        $row[$column] = $lastName . ' ' . mb_substr($firstName, 0, 1) . '.' . mb_substr($patronymic, 0, 1) . '.';
                        break;
                    case PatternDetector::PATTERN_NAME:
                        $row[$column] = $lastName . ' ' . $firstName;
                        break;
                    case PatternDetector::PATTERN_EMAIL:
                        $row[$column] = $this->generateEmail($firstName, $lastName);
                        break;
                    case PatternDetector::PATTERN_PHONE:
                        $row[$column] = $this->generatePhone((string) $row[$column]);
                        break;
                    case PatternDetector::PATTERN_FIRSTNAME:
                        $row[$column] = $firstName;
                        break;
                    case PatternDetector::PATTERN_LASTNAME:
                        $row[$column] = $lastName;
                        break;
                    case PatternDetector::PATTERN_PATRONYMIC:
                        $row[$column] = $patronymic;
                        break;
                    case PatternDetector::PATTERN_GENDER:
                        $row[$column] = $this->generateGender($gender, (string) $row[$column]);
                        break;
                }
            }
        }
        unset($row);

        return $rows;
    }

    /** Генерирует email из транслитерированных имени и фамилии. */
    private function generateEmail(string $firstName, string $lastName): string
    {
        $translitFirst = $this->transliterate(mb_strtolower($firstName));
        $translitLast = $this->transliterate(mb_strtolower($lastName));
        $domain = self::EMAIL_DOMAINS[mt_rand(0, count(self::EMAIL_DOMAINS) - 1)];
        $num = mt_rand(1, 999);

        return $translitFirst . '.' . $translitLast . $num . '@' . $domain;
    }

    /** Генерирует российский мобильный номер, сохраняя формат оригинала. */
    private function generatePhone(string $originalPhone = ''): string
    {
        // 10 новых цифр: 9 + 9 случайных
        $newDigits = '9' . str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);

        if ($originalPhone === '') {
            return '7' . $newDigits;
        }

        // Шаблон: каждая цифра → #
        /** @var string $template */
        $template = preg_replace('/\d/', '#', $originalPhone);
        $placeholderCount = substr_count($template, '#');

        if ($placeholderCount === 11) {
            // Есть цифра префикса (7 или 8) — сохраняем её
            preg_match('/\d/', $originalPhone, $m);
            $allDigits = (isset($m[0]) ? $m[0] : '7') . $newDigits;
        } elseif ($placeholderCount === 10) {
            $allDigits = $newDigits;
        } else {
            return '7' . $newDigits; // fallback
        }

        // Заполнить шаблон цифрами
        $result = '';
        $digitIndex = 0;
        for ($i = 0, $len = strlen($template); $i < $len; $i++) {
            if ($template[$i] === '#') {
                $result .= $allDigits[$digitIndex];
                $digitIndex++;
            } else {
                $result .= $template[$i];
            }
        }

        return $result;
    }

    /** Генерирует замену значения пола, сохраняя формат и регистр оригинала. */
    private function generateGender(int $gender, string $originalValue): string
    {
        $normalized = mb_strtolower(trim($originalValue));

        if (!isset(self::GENDER_MAP[$normalized])) {
            return $originalValue;
        }

        $pair = self::GENDER_MAP[$normalized];
        $replacement = $pair[$gender];

        return $this->matchCase($replacement, trim($originalValue));
    }

    /** Приводит регистр $value к регистру $reference. */
    private function matchCase(string $value, string $reference): string
    {
        if (mb_strlen($reference) > 1 && mb_strtoupper($reference) === $reference) {
            return mb_strtoupper($value);
        }

        $firstChar = mb_substr($reference, 0, 1);
        if (mb_strtoupper($firstChar) === $firstChar) {
            return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
        }

        return $value;
    }

    /** Транслитерирует кириллический текст в латиницу. */
    private function transliterate(string $text): string
    {
        return strtr($text, self::TRANSLIT_MAP);
    }
}
