<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Разбор полей JSON-снимка с проверкой типов и лишних ключей.
 */
final class LorePayload
{
    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Отвергает неизвестные ключи снимка.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param array<int, string> $allowedKeys Разрешённые ключи.
     *
     * @return void
     *
     * @throws LoreInvalidException Если ключ неизвестен.
     */
    public static function assertKnownKeys(array $payload, array $allowedKeys): void
    {
        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !in_array($key, $allowedKeys, true)) {
                throw new LoreInvalidException('Lore payload has an unknown key ' . (string) $key);
            }
        }
    }

    /**
     * Проверяет границы страницы.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws LoreInvalidException Если границы неверны.
     */
    public static function assertPageBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new LoreInvalidException('Lore page bounds are invalid');
        }
    }

    /**
     * Подстрока поиска без пробелов по краям.
     *
     * @param string|null $searchQuery Подстрока.
     *
     * @return string|null Подстрока или null.
     *
     * @throws LoreInvalidException Если подстрока длиннее 100.
     */
    public static function searchQuery(?string $searchQuery): ?string
    {
        if ($searchQuery === null) {
            return null;
        }

        $trimmed = trim($searchQuery);
        if (mb_strlen($trimmed, 'UTF-8') > 100) {
            throw new LoreInvalidException('Lore search query is too long');
        }

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Обязательная строка.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     *
     * @return string Значение.
     *
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    public static function requireString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            throw new LoreInvalidException('Lore ' . $key . ' must be a string');
        }

        return $value;
    }

    /**
     * Строка с умолчанием.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     * @param string $default Значение по умолчанию.
     *
     * @return string Значение.
     *
     * @throws LoreInvalidException Если тип неверен.
     */
    public static function optionalString(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return $default;
        }

        if (!is_string($value)) {
            throw new LoreInvalidException('Lore ' . $key . ' must be a string');
        }

        return $value;
    }

    /**
     * Строка или null с обрезкой пустой строки.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     *
     * @return string|null Значение или null.
     *
     * @throws LoreInvalidException Если тип неверен.
     */
    public static function stringOrNull(array $payload, string $key): ?string
    {
        $value = self::optionalString($payload, $key, '');

        return $value === '' ? null : $value;
    }

    /**
     * Целое с умолчанием.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     * @param int $default Значение по умолчанию.
     *
     * @return int Значение.
     *
     * @throws LoreInvalidException Если тип неверен.
     */
    public static function intValue(array $payload, string $key, int $default = 0): int
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return $default;
        }

        $intValue = is_int($value) ? $value : null;
        if ($intValue === null) {
            throw new LoreInvalidException('Lore ' . $key . ' must be an integer');
        }

        return $intValue;
    }

    /**
     * Список целых.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     *
     * @return array<int, int> Уникальные значения.
     *
     * @throws LoreInvalidException Если элемент не целое.
     */
    public static function intList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new LoreInvalidException('Lore ' . $key . ' must be a list of integers');
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new LoreInvalidException('Lore ' . $key . ' must be a list of integers');
            }

            $list[$item] = $item;
        }

        return array_values($list);
    }
}
