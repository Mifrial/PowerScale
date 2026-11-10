<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;

/**
 * Разбор и проверка скалярных полей снимка локации.
 *
 * Ошибки содержат путь к полю (`boards[3].name`), чтобы UI подсветил объект.
 */
final class SceneSaveFields
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
     * @throws SceneInvalidException Если id неверен.
     */
    public function identifier(array $row, string $key, string $path): int
    {
        $value = $this->intValue($row, $key, $path, PHP_INT_MIN);
        if ($value === 0) {
            throw new SceneInvalidException($path . ' is zero');
        }

        return $value;
    }

    /**
     * Необязательный идентификатор: null или ненулевое целое.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return int|null Id или null.
     *
     * @throws SceneInvalidException Если id неверен.
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
     * @throws SceneInvalidException Если значение неверно.
     */
    public function intValue(array $row, string $key, string $path, int $min, ?int $max = null): int
    {
        $value = $row[$key] ?? null;
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (!is_int($value)) {
            throw new SceneInvalidException($path . ' is not an integer');
        }

        if ($value < $min || ($max !== null && $value > $max)) {
            throw new SceneInvalidException($path . ' is out of range');
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
     * @throws SceneInvalidException Если значение неверно.
     */
    public function optionalIntValue(array $row, string $key, string $path, int $min): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->intValue($row, $key, $path, $min);
    }

    /**
     * Целое с умолчанием.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $default Умолчание.
     * @param int $min Минимум.
     * @param int|null $max Максимум.
     *
     * @return int Значение.
     *
     * @throws SceneInvalidException Если значение неверно.
     */
    public function intOrDefault(array $row, string $key, string $path, int $default, int $min, ?int $max = null): int
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return $default;
        }

        return $this->intValue($row, $key, $path, $min, $max);
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
     * @throws SceneInvalidException Если значение не строка.
     */
    public function stringValue(array $row, string $key, string $path): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new SceneInvalidException($path . ' is not a string');
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
     * @throws SceneInvalidException Если значение не строка.
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
     * @throws SceneInvalidException Если значение не bool.
     */
    public function boolValue(array $row, string $key, string $path): bool
    {
        $value = $row[$key] ?? null;
        if (!is_bool($value)) {
            throw new SceneInvalidException($path . ' is not a boolean');
        }

        return $value;
    }

    /**
     * Обязательный JSON-объект.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array<string|int, mixed> Массив.
     *
     * @throws SceneInvalidException Если значение не массив.
     */
    public function arrayValue(array $row, string $key, string $path): array
    {
        $value = $row[$key] ?? null;
        if (!is_array($value)) {
            throw new SceneInvalidException($path . ' is not an object');
        }

        return $value;
    }

    /**
     * Необязательный JSON-объект.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array<string|int, mixed> Массив; пустой, если поля нет.
     *
     * @throws SceneInvalidException Если значение не массив.
     */
    public function optionalArrayValue(array $row, string $key, string $path): array
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return [];
        }

        return $this->arrayValue($row, $key, $path);
    }

    /**
     * Список строк снимка.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array<int, array<string|int, mixed>> Строки.
     *
     * @throws SceneInvalidException Если это не list объектов.
     */
    public function rows(array $payload, string $key, string $path): array
    {
        $value = $payload[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new SceneInvalidException($path . ' is not a list');
        }

        $rows = [];
        foreach ($value as $index => $row) {
            if (!is_array($row)) {
                throw new SceneInvalidException($path . '[' . $index . '] is not an object');
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
