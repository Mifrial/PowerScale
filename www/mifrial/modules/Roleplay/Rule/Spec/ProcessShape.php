<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Обязательные поля процесса: непустая строка и целое.
 */
final class ProcessShape
{
    /**
     * Обязательная непустая строка.
     *
     * @param array<string, mixed> $row Объект.
     * @param string $key Ключ.
     * @param string $shapeKey Ключ формы для ошибки.
     *
     * @return string Значение.
     *
     * @throws RuleSpecShapeException Если ключа нет или он пустой.
     */
    public static function requiredString(array $row, string $key, string $shapeKey): string
    {
        $value = SpecShape::string($row, $key);
        if ($value === '') {
            throw new RuleSpecShapeException($shapeKey);
        }

        return $value;
    }

    /**
     * Обязательное целое.
     *
     * @param array<string, mixed> $row Объект.
     * @param string $key Ключ.
     * @param string $shapeKey Ключ формы для ошибки.
     *
     * @return int Значение.
     *
     * @throws RuleSpecShapeException Если ключа нет или он не целое.
     */
    public static function requiredInt(array $row, string $key, string $shapeKey): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new RuleSpecShapeException($shapeKey);
        }

        return $row[$key];
    }
}
