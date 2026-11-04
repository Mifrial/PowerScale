<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Dto;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;

/**
 * Срез `files` из local.php.
 */
final class FileSettings
{
    private const MAX_SIZE_LIMIT = 33554432;

    private const DEFAULT_MAX_SIZE = 33554432;

    private const DEFAULT_MIME = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /**
     * Собирает настройки.
     *
     * @param string $path Корень диска.
     * @param int $maxSize Общий потолок байт.
     * @param array<int, string> $mime Разрешённые типы.
     *
     * @return void
     */
    private function __construct(
        private readonly string $path,
        private readonly int $maxSize,
        private readonly array $mime,
    ) {
    }

    /**
     * Разбирает срез конфига; пустой массив — дефолты.
     *
     * @param mixed $section Значение ключа files.
     * @param string $defaultPath Путь по умолчанию.
     *
     * @return self Настройки.
     *
     * @throws SetupException Если срез или поле недопустимы.
     */
    public static function fromSection(mixed $section, string $defaultPath): self
    {
        if ($section === null) {
            $section = [];
        }

        if (!is_array($section)) {
            throw new SetupException('SETUP_INVALID', 'files config must be an array');
        }

        return new self(
            self::pathValue($section['path'] ?? null, $defaultPath),
            self::maxSizeValue($section['max_size'] ?? null),
            self::mimeValue($section['mime'] ?? null),
        );
    }

    /**
     * Корень диска.
     *
     * @return string Путь.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Общий потолок байт.
     *
     * @return int Лимит.
     */
    public function maxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Разрешённые серверные типы.
     *
     * @return array<int, string> Список mime.
     */
    public function mime(): array
    {
        return $this->mime;
    }

    /**
     * Входит ли тип в политику хранилища.
     *
     * @param string $mime Тип из finfo.
     *
     * @return bool true, если разрешён.
     */
    public function allowsMime(string $mime): bool
    {
        return in_array($mime, $this->mime, true);
    }

    /**
     * Путь из конфига или дефолт.
     *
     * @param mixed $pathValue Значение path.
     * @param string $defaultPath Дефолт.
     *
     * @return string Путь.
     *
     * @throws SetupException Если значение пустое или не строка.
     */
    private static function pathValue(mixed $pathValue, string $defaultPath): string
    {
        if ($pathValue === null) {
            return $defaultPath;
        }

        if (!is_string($pathValue) || trim($pathValue) === '') {
            throw new SetupException('SETUP_INVALID', 'files.path must be a non-empty string');
        }

        return trim($pathValue);
    }

    /**
     * Потолок размера.
     *
     * @param mixed $maxSizeValue Значение max_size.
     *
     * @return int Лимит.
     *
     * @throws SetupException Если значение вне 1..33554432.
     */
    private static function maxSizeValue(mixed $maxSizeValue): int
    {
        if ($maxSizeValue === null) {
            return self::DEFAULT_MAX_SIZE;
        }

        if (!is_int($maxSizeValue) || $maxSizeValue < 1 || $maxSizeValue > self::MAX_SIZE_LIMIT) {
            throw new SetupException('SETUP_INVALID', 'files.max_size must be 1..33554432');
        }

        return $maxSizeValue;
    }

    /**
     * Список разрешённых типов.
     *
     * @param mixed $mimeValue Значение mime.
     *
     * @return array<int, string> Mime.
     *
     * @throws SetupException Если список пуст или содержит не тип.
     */
    private static function mimeValue(mixed $mimeValue): array
    {
        if ($mimeValue === null) {
            return self::DEFAULT_MIME;
        }

        if (!is_array($mimeValue) || $mimeValue === []) {
            throw new SetupException('SETUP_INVALID', 'files.mime must be a non-empty list');
        }

        $mimeList = [];
        foreach ($mimeValue as $mime) {
            if (!is_string($mime) || preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $mime) !== 1) {
                throw new SetupException('SETUP_INVALID', 'files.mime must contain type/subtype strings');
            }

            $mimeList[$mime] = true;
        }

        return array_keys($mimeList);
    }
}
