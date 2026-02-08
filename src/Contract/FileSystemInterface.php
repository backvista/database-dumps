<?php

namespace SmartCrm\DatabaseDumps\Contract;

/**
 * Интерфейс для работы с файловой системой
 *
 * Абстракция для тестируемости и возможности подмены реализации
 */
interface FileSystemInterface
{
    /**
     * Проверить существование файла или директории
     */
    public function exists(string $path): bool;

    /**
     * Прочитать содержимое файла
     */
    public function read(string $path): string;

    /**
     * Записать содержимое в файл
     */
    public function write(string $path, string $content): void;

    /**
     * Найти файлы по паттерну в директории
     *
     * @return array<string> Массив абсолютных путей к файлам
     */
    public function findFiles(string $directory, string $pattern): array;

    /**
     * Создать директорию рекурсивно
     */
    public function createDirectory(string $path): void;

    /**
     * Проверить, является ли путь директорией
     */
    public function isDirectory(string $path): bool;

    /**
     * Получить размер файла в байтах
     */
    public function getFileSize(string $path): int;
}
