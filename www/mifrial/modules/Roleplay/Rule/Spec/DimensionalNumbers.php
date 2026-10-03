<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разбор пары base и size.
 */
final class DimensionalNumbers
{
    /**
     * Размерное число. Нет объекта — null.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return DimensionalNumber|null Число или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function optional(array $document, string $key): ?DimensionalNumber
    {
        $value = SpecShape::object($document, $key);

        return $value === null ? null : self::pair($value, $key);
    }

    /**
     * Размерное число. Нет ключа — нули.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return DimensionalNumber Число.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function required(array $document, string $key): DimensionalNumber
    {
        $value = SpecShape::object($document, $key);

        return $value === null ? new DimensionalNumber(0, 0) : self::pair($value, $key);
    }

    /**
     * Число шкалы характеристик. Нет ключа — нули.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return CharacteristicNumber Число.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function characteristic(array $document, string $key): CharacteristicNumber
    {
        $value = SpecShape::object($document, $key);
        if ($value === null) {
            return new CharacteristicNumber(0, 0);
        }

        $pair = self::pair($value, $key);

        return new CharacteristicNumber($pair->getBase(), $pair->getSize());
    }

    /**
     * Пара из объекта.
     *
     * @param array<string, mixed> $value Объект.
     * @param string $key Ключ.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws RuleSpecShapeException Если base или size не int.
     */
    public static function pair(array $value, string $key): DimensionalNumber
    {
        return new DimensionalNumber(SpecShape::int($value, 'base'), SpecShape::int($value, 'size'));
    }
}
