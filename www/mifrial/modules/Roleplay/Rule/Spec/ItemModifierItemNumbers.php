<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Числовые поля предмета: множители, слагаемые и сдвиги по шкале.
 */
final class ItemModifierItemNumbers
{
    /**
     * Вес после множителя и слагаемого.
     *
     * @param DimensionalNumber|null $weight Исходный.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public static function weight(?DimensionalNumber $weight, ?array $change): ?DimensionalNumber
    {
        if ($weight === null || $change === null) {
            return $weight;
        }

        return self::scaled($weight, $change, false);
    }

    /**
     * Прочность, сила или ловкость по шкале характеристики.
     *
     * @param DimensionalNumber|null $number Число или null.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public static function characteristic(?DimensionalNumber $number, ?array $change): ?DimensionalNumber
    {
        if ($number === null || $change === null) {
            return $number;
        }

        return self::scaled($number, $change, true);
    }

    /**
     * Минимальная сила как CharacteristicNumber.
     *
     * @param CharacteristicNumber|null $number Исходное.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return CharacteristicNumber|null Число или null.
     */
    public static function characteristicNumber(
        ?CharacteristicNumber $number,
        ?array $change,
    ): ?CharacteristicNumber {
        if ($number === null || $change === null) {
            return $number;
        }

        $scaled = self::scaled($number, $change, true);

        return new CharacteristicNumber($scaled->getBase(), $scaled->getSize());
    }

    /**
     * Множитель к базе, затем слагаемое.
     *
     * @param DimensionalNumber $number Число.
     * @param array{factor: float, delta: int, size: int} $change Итог.
     * @param bool $characteristic Признак: true — слагаемое по шкале характеристики.
     *
     * @return DimensionalNumber Число.
     */
    public static function scaled(DimensionalNumber $number, array $change, bool $characteristic): DimensionalNumber
    {
        $base = (int) round($number->getBase() * $change['factor']);
        if (!$characteristic) {
            return new DimensionalNumber($base + $change['delta'], $number->getSize() + $change['size']);
        }

        return self::shifted($base, $number->getSize() + $change['size'], $change['delta']);
    }

    /**
     * Сдвиг по шкале характеристики. Ноль оставляет базу.
     *
     * @param int $base База после множителя.
     * @param int $size Размер.
     * @param int $delta Слагаемое.
     *
     * @return DimensionalNumber Число.
     */
    public static function shifted(int $base, int $size, int $delta): DimensionalNumber
    {
        $scaled = new CharacteristicNumber($base, $size);
        if ($delta === 0) {
            return $scaled;
        }

        return $scaled->modify($delta);
    }

    /**
     * Целое поле: множитель, затем слагаемое.
     *
     * @param int $value Значение.
     * @param array{factor: float, delta: int, size: int} $change Итог.
     *
     * @return int Число.
     */
    public static function scaledInt(int $value, array $change): int
    {
        return (int) round($value * $change['factor']) + $change['delta'];
    }
}
