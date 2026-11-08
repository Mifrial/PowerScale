<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Разбор и проверка скалярных полей снимка карты.
 *
 * Ошибки содержат путь к полю (`nodes[3].name`), чтобы UI подсветил объект.
 */
final class MapSaveFields
{
    /**
     * Идентификатор сущности: целое, не ноль; отрицательный — новая.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если id неверен.
     */
    public function identifier(array $row, string $key, string $path): int
    {
        $value = $this->intValue($row, $key, $path, PHP_INT_MIN);
        if ($value === 0) {
            throw new MapInvalidException($path . ' is zero');
        }

        return $value;
    }

    /**
     * Необязательный идентификатор: null или ненулевое целое; отрицательный — новый.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return int|null Id или null.
     *
     * @throws MapInvalidException Если id неверен.
     */
    public function optionalIdentifier(array $row, string $key, string $path): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->identifier($row, $key, $path);
    }

    /**
     * Целое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум.
     * @param int|null $max Максимум.
     *
     * @return int Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    public function intValue(array $row, string $key, string $path, int $min, ?int $max = null): int
    {
        $value = $row[$key] ?? null;
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (!is_int($value)) {
            throw new MapInvalidException($path . ' is not an integer');
        }

        if ($value < $min || ($max !== null && $value > $max)) {
            throw new MapInvalidException($path . ' is out of range');
        }

        return $value;
    }

    /**
     * Необязательное целое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум.
     *
     * @return int|null Значение или null.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    public function optionalIntValue(array $row, string $key, string $path, int $min): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->intValue($row, $key, $path, $min);
    }

    /**
     * Строковое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string Значение.
     *
     * @throws MapInvalidException Если значение не строка.
     */
    public function stringValue(array $row, string $key, string $path): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new MapInvalidException($path . ' is not a string');
        }

        return $value;
    }

    /**
     * Необязательное строковое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string|null Значение или null.
     *
     * @throws MapInvalidException Если значение не строка.
     */
    public function optionalStringValue(array $row, string $key, string $path): ?string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return $this->stringValue($row, $key, $path);
    }

    /**
     * Булево поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return bool Значение.
     *
     * @throws MapInvalidException Если значение не bool.
     */
    public function boolValue(array $row, string $key, string $path): bool
    {
        $value = $row[$key] ?? null;
        if (!is_bool($value)) {
            throw new MapInvalidException($path . ' is not a boolean');
        }

        return $value;
    }

    /**
     * Цвет `#rrggbb`.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string Цвет.
     *
     * @throws MapInvalidException Если цвет неверен.
     */
    public function colorValue(array $row, string $key, string $path): string
    {
        $color = trim($this->stringValue($row, $key, $path));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException($path . ' is invalid');
        }

        return $color;
    }

    /**
     * Цвет границы: пусто — границы нет, иначе `#rrggbb`.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string Цвет или пустая строка.
     *
     * @throws MapInvalidException Если цвет неверен.
     */
    public function strokeValue(array $row, string $key, string $path): string
    {
        $color = trim($this->stringValue($row, $key, $path));
        if ($color === '') {
            return '';
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException($path . ' is invalid');
        }

        return strtolower($color);
    }
}
