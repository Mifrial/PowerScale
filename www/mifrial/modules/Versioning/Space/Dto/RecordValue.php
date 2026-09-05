<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Чтение полей Record из карты ST или JSON.
 */
final class RecordValue
{
    /**
     * Целое поле.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return int Значение.
     *
     * @throws SpaceInvalidException Если нет или не int.
     */
    public static function requireInt(array $fields, string $fieldName): int
    {
        if (!array_key_exists($fieldName, $fields) || !is_int($fields[$fieldName])) {
            throw new SpaceInvalidException('Record field is incomplete');
        }

        return $fields[$fieldName];
    }

    /**
     * Строка.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return string Значение.
     *
     * @throws SpaceInvalidException Если нет или не строка.
     */
    public static function requireString(array $fields, string $fieldName): string
    {
        if (!array_key_exists($fieldName, $fields) || !is_string($fields[$fieldName])) {
            throw new SpaceInvalidException('Record field is incomplete');
        }

        return $fields[$fieldName];
    }

    /**
     * Булево.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return bool Значение.
     *
     * @throws SpaceInvalidException Если нет или не bool.
     */
    public static function requireBool(array $fields, string $fieldName): bool
    {
        if (!array_key_exists($fieldName, $fields) || !is_bool($fields[$fieldName])) {
            throw new SpaceInvalidException('Record field is incomplete');
        }

        return $fields[$fieldName];
    }

    /**
     * DateTime ядра.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return DateTime Момент.
     *
     * @throws SpaceInvalidException Если нет или не DateTime.
     */
    public static function requireDateTime(array $fields, string $fieldName): DateTime
    {
        if (!array_key_exists($fieldName, $fields) || !$fields[$fieldName] instanceof DateTime) {
            throw new SpaceInvalidException('Record field is incomplete');
        }

        return $fields[$fieldName];
    }
}
