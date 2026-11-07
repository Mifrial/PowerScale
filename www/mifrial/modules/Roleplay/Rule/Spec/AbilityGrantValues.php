<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Числовые значения грантов: целое, размерное число или формула.
 */
final class AbilityGrantValues
{
    /**
     * Целое или размерное число.
     *
     * @param array<string, mixed> $row Объект.
     * @param string $key Ключ.
     *
     * @return int|DimensionalNumber Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function intOrNumber(array $row, string $key): int|DimensionalNumber
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return 0;
        }

        $value = $row[$key];
        if (is_int($value)) {
            return $value;
        }

        return DimensionalNumbers::pair(SpecShape::requireMap($value, $key), $key);
    }

    /**
     * Величина сопротивления.
     *
     * @param array<string, mixed> $row Объект.
     *
     * @return DimensionalNumber|DimensionalFormula|ScalarFormula Величина.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function resistanceValue(array $row): DimensionalNumber|DimensionalFormula|ScalarFormula
    {
        $value = SpecShape::object($row, 'value');
        if ($value === null) {
            return new DimensionalNumber(0, 0);
        }

        if (!array_key_exists('type', $value)) {
            return DimensionalNumbers::pair($value, 'value');
        }

        $type = SpecShape::string($value, 'type');

        return in_array($type, ['dimensional', 'characteristic', 'actionCharacteristic'], true)
            ? Formulas::dimensionalNode($value)
            : Formulas::scalarNode($value);
    }
}
