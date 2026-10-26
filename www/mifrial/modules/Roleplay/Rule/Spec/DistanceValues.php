<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\DistanceRange;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DistanceValue;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Разбор дистанции профиля: {min, max} и falloff.
 */
final class DistanceValues
{
    /**
     * Обязательный диапазон дистанции.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return DistanceRange Диапазон.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function range(array $document, string $key = 'distance'): DistanceRange
    {
        $part = SpecShape::object($document, $key);
        if ($part === null) {
            throw new RuleSpecShapeException($key);
        }

        $min = SpecShape::object($part, 'min');
        if ($min === null) {
            throw new RuleSpecShapeException($key . '.min');
        }

        $max = SpecShape::object($part, 'max');

        return new DistanceRange(
            self::value($min, $key . '.min'),
            $max === null ? null : self::value($max, $key . '.max'),
        );
    }

    /**
     * Необязательное значение дистанции.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return DistanceValue|null Значение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function optionalValue(array $document, string $key): ?DistanceValue
    {
        $part = SpecShape::object($document, $key);

        return $part === null ? null : self::value($part, $key);
    }

    /**
     * Одно значение дистанции.
     *
     * @param array<string, mixed> $row Значение.
     * @param string $key Ключ для ошибки.
     *
     * @return DistanceValue Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function value(array $row, string $key): DistanceValue
    {
        $type = SpecShape::string($row, 'type');
        if (!in_array($type, [DistanceValue::TYPE_STEP, DistanceValue::TYPE_IPARI], true)) {
            throw new RuleSpecShapeException($key . '.type');
        }

        $value = SpecShape::object($row, 'value');
        if ($value === null) {
            throw new RuleSpecShapeException($key . '.value');
        }

        return new DistanceValue($type, Formulas::dimensionalNode($value));
    }
}
