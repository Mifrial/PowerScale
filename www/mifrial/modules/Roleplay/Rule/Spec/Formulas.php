<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\CharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\AbilityLevelScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizeGapScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizePositiveScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizeScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\FixedScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterFloorDivScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ToScalar;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Разбор размерной и скалярной формулы.
 */
final class Formulas
{
    /**
     * Размерная формула. Нет ключа — узел числа 0.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return DimensionalFormula Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function dimensional(array $document, string $key): DimensionalFormula
    {
        $value = SpecShape::object($document, $key);

        return $value === null ? new FixedNode(0) : self::dimensionalNode($value);
    }

    /**
     * Скалярная формула. Нет ключа — число 0. Голое число — то же.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return ScalarFormula Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function scalar(array $document, string $key): ScalarFormula
    {
        if (!array_key_exists($key, $document) || $document[$key] === null) {
            return new FixedScalar(0);
        }

        $value = $document[$key];
        if (is_int($value)) {
            return new FixedScalar($value);
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new RuleSpecShapeException($key);
        }

        return self::scalarNode($value);
    }

    /**
     * Узел размерной формулы.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return DimensionalFormula Узел.
     *
     * @throws RuleSpecShapeException Если type чужой.
     */
    public static function dimensionalNode(array $node): DimensionalFormula
    {
        $type = SpecShape::string($node, 'type');

        return match ($type) {
            'fixed' => new FixedNode(SpecShape::int($node, 'value')),
            'dimensional' => new DimensionalNode(DimensionalNumbers::pair($node, 'dimensional')),
            'characteristic' => self::characteristicNode($node),
            'actionCharacteristic' => self::actionNode($node),
            default => throw new RuleSpecShapeException('type'),
        };
    }

    /**
     * Узел скалярной формулы.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return ScalarFormula Узел.
     *
     * @throws RuleSpecShapeException Если type чужой.
     */
    public static function scalarNode(array $node): ScalarFormula
    {
        $type = SpecShape::string($node, 'type');

        return match ($type) {
            'fixed' => new FixedScalar(SpecShape::int($node, 'value')),
            'parameter' => new ParameterScalar(SpecShape::string($node, 'parameter_code'), SpecShape::int($node, 'per_unit')),
            'parameter_floor_div' => self::floorDiv($node),
            'ability_level' => self::abilityLevel($node),
            'to_scalar' => new ToScalar(self::dimensional($node, 'value')),
            'characteristic_size' => new CharacteristicSizeScalar(SpecShape::string($node, 'characteristic_code')),
            'characteristic_size_positive' => new CharacteristicSizePositiveScalar(SpecShape::string($node, 'characteristic_code')),
            'characteristic_size_gap' => self::sizeGap($node),
            default => throw new RuleSpecShapeException('type'),
        };
    }

    /**
     * Узел характеристики.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return CharacteristicNode Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function characteristicNode(array $node): CharacteristicNode
    {
        return new CharacteristicNode(SpecShape::string($node, 'characteristic_code'), SpecShape::int($node, 'modifier'));
    }

    /**
     * Узел характеристики действия.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return ActionCharacteristicNode Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function actionNode(array $node): ActionCharacteristicNode
    {
        return new ActionCharacteristicNode(
            SpecShape::string($node, 'action'),
            SpecShape::string($node, 'characteristic'),
            self::optionalInt($node, 'multiplier'),
            self::modifiers($node),
        );
    }

    /**
     * Модификаторы действия.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return array<int, ActionCharacteristicModifier> Список.
     *
     * @throws RuleSpecShapeException Если строка чужая.
     */
    private static function modifiers(array $node): array
    {
        $rows = [];
        foreach (SpecShape::list($node, 'modifier') as $row) {
            $rows[] = self::modifier($row);
        }

        return $rows;
    }

    /**
     * Один модификатор.
     *
     * @param mixed $row Строка.
     *
     * @return ActionCharacteristicModifier Модификатор.
     *
     * @throws RuleSpecShapeException Если строка не объект.
     */
    private static function modifier(mixed $row): ActionCharacteristicModifier
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('modifier');
        }

        return new ActionCharacteristicModifier(
            SpecShape::int($row, 'delta'),
            SpecShape::optionalString($row, 'source_code'),
            SpecShape::optionalString($row, 'source_label'),
        );
    }

    /**
     * Узел деления параметра.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return ParameterFloorDivScalar Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function floorDiv(array $node): ParameterFloorDivScalar
    {
        return new ParameterFloorDivScalar(SpecShape::string($node, 'parameter_code'), SpecShape::int($node, 'divisor'));
    }

    /**
     * Узел уровня способности.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return AbilityLevelScalar Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function abilityLevel(array $node): AbilityLevelScalar
    {
        return new AbilityLevelScalar(
            SpecShape::string($node, 'ability_code'),
            self::optionalInt($node, 'multiplier'),
            self::optionalInt($node, 'offset'),
        );
    }

    /**
     * Разрыв размеров.
     *
     * @param array<string, mixed> $node Объект.
     *
     * @return CharacteristicSizeGapScalar Узел.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function sizeGap(array $node): CharacteristicSizeGapScalar
    {
        return new CharacteristicSizeGapScalar(
            SpecShape::string($node, 'characteristic_code_from'),
            SpecShape::string($node, 'characteristic_code_to'),
        );
    }

    /**
     * Необязательное целое.
     *
     * @param array<string, mixed> $node Объект.
     * @param string $key Ключ.
     *
     * @return int|null Число или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function optionalInt(array $node, string $key): ?int
    {
        return SpecShape::optionalInt($node, $key);
    }
}
