<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Чтение колонок нормализованной строки для DTO.
 *
 * Сообщение исключения передаёт вызывающий DTO, поэтому текст ошибки
 * остаётся прежним, а проверка типов — одна на все строки.
 */
final class GameRowFields
{
    /**
     * Обязательное целое.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если колонки нет или она не int.
     */
    public static function int(array $fields, string $key, string $message): int
    {
        $value = $fields[$key] ?? null;
        if (!is_int($value)) {
            throw new GameInvalidException($message);
        }

        return $value;
    }

    /**
     * Целое или null.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return int|null Значение или null.
     *
     * @throws GameInvalidException Если значение не int и не null.
     */
    public static function nullableInt(array $fields, string $key, string $message): ?int
    {
        if (($fields[$key] ?? null) === null) {
            return null;
        }

        return self::int($fields, $key, $message);
    }

    /**
     * Обязательная строка.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если колонки нет или она не string.
     */
    public static function string(array $fields, string $key, string $message): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value)) {
            throw new GameInvalidException($message);
        }

        return $value;
    }

    /**
     * Непустая строка.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если колонки нет, она не string или пустая.
     */
    public static function nonEmptyString(array $fields, string $key, string $message): string
    {
        $value = self::string($fields, $key, $message);
        if ($value === '') {
            throw new GameInvalidException($message);
        }

        return $value;
    }

    /**
     * Строка или null.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return string|null Значение или null.
     *
     * @throws GameInvalidException Если значение не string и не null.
     */
    public static function nullableString(array $fields, string $key, string $message): ?string
    {
        if (($fields[$key] ?? null) === null) {
            return null;
        }

        return self::string($fields, $key, $message);
    }

    /**
     * Обязательный момент.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return DateTime Значение.
     *
     * @throws GameInvalidException Если колонки нет или она не DateTime.
     */
    public static function dateTime(array $fields, string $key, string $message): DateTime
    {
        $value = $fields[$key] ?? null;
        if (!$value instanceof DateTime) {
            throw new GameInvalidException($message);
        }

        return $value;
    }

    /**
     * Строка, где null читается как пустая.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     * @param string $message Текст ошибки.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если значение не string и не null.
     */
    public static function text(array $fields, string $key, string $message): string
    {
        return ($fields[$key] ?? null) === null ? '' : self::string($fields, $key, $message);
    }
}
