<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Чтение одного ключа документа: нет ключа — пустое значение, чужая форма — отказ.
 */
final class SpecShape
{
    /**
     * Список. Нет ключа или null — пустой список.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return array<int|string, mixed> Список.
     *
     * @throws RuleSpecShapeException Если ключ не список.
     */
    public static function list(array $document, string $key): array
    {
        $value = self::optional($document, $key);
        if ($value === null) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Объект. Нет ключа или null — null.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return array<string, mixed>|null Объект или null.
     *
     * @throws RuleSpecShapeException Если ключ не объект.
     */
    public static function object(array $document, string $key): ?array
    {
        $value = self::optional($document, $key);
        if ($value === null) {
            return null;
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Строка. Нет ключа — пустая строка.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return string Значение.
     *
     * @throws RuleSpecShapeException Если ключ не строка.
     */
    public static function string(array $document, string $key): string
    {
        $value = self::optional($document, $key);
        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Строка или null. Нет ключа — null.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return string|null Значение.
     *
     * @throws RuleSpecShapeException Если ключ не строка.
     */
    public static function optionalString(array $document, string $key): ?string
    {
        if (!self::present($document, $key)) {
            return null;
        }

        $value = $document[$key];
        if (!is_string($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Целое. Нет ключа — 0.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return int Значение.
     *
     * @throws RuleSpecShapeException Если ключ не int.
     */
    public static function int(array $document, string $key): int
    {
        $value = self::optional($document, $key);
        if ($value === null) {
            return 0;
        }

        if (!is_int($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Целое или null.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return int|null Значение.
     *
     * @throws RuleSpecShapeException Если ключ не int.
     */
    public static function optionalInt(array $document, string $key): ?int
    {
        if (!self::present($document, $key)) {
            return null;
        }

        $value = $document[$key];
        if (!is_int($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Bool. Нет ключа — false.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return bool Значение.
     *
     * @throws RuleSpecShapeException Если ключ не bool.
     */
    public static function bool(array $document, string $key): bool
    {
        $value = self::optional($document, $key);
        if ($value === null) {
            return false;
        }

        if (!is_bool($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Число int или float. Нет ключа — 0.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return int|float Значение.
     *
     * @throws RuleSpecShapeException Если ключ не число.
     */
    public static function number(array $document, string $key): int|float
    {
        $value = self::optional($document, $key);
        if ($value === null) {
            return 0;
        }

        if (!is_int($value) && !is_float($value)) {
            throw new RuleSpecShapeException($key);
        }

        return $value;
    }

    /**
     * Список строк. Нет ключа — пустой список.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return array<int, string> Строки.
     *
     * @throws RuleSpecShapeException Если элемент не строка.
     */
    public static function stringList(array $document, string $key): array
    {
        $codes = [];
        foreach (self::list($document, $key) as $value) {
            if (!is_string($value)) {
                throw new RuleSpecShapeException($key);
            }

            $codes[] = $value;
        }

        return $codes;
    }

    /**
     * Список целых. Нет ключа — пустой список.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return array<int, int> Числа.
     *
     * @throws RuleSpecShapeException Если элемент не int.
     */
    public static function intList(array $document, string $key): array
    {
        $numbers = [];
        foreach (self::list($document, $key) as $value) {
            if (!is_int($value)) {
                throw new RuleSpecShapeException($key);
            }

            $numbers[] = $value;
        }

        return $numbers;
    }

    /**
     * Ключ есть и значение не null.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return bool true, если значение задано.
     */
    private static function present(array $document, string $key): bool
    {
        return array_key_exists($key, $document) && $document[$key] !== null;
    }

    /**
     * Значение ключа или null, если ключа нет.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return mixed Значение или null.
     */
    private static function optional(array $document, string $key): mixed
    {
        if (!self::present($document, $key)) {
            return null;
        }

        return $document[$key];
    }
}
