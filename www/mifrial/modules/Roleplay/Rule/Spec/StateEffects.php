<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\State\CharacteristicDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CharacteristicModifyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CheckAdvantageEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CheckDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\DamageOverTimeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\DimensionalDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\FixedDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\ResourceLimitModifyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\ResourceLimitSetEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\StateDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\StateEffect;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Эффекты состояния.
 */
final class StateEffects
{
    /**
     * Список эффектов.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, StateEffect> Эффекты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function list(array $document): array
    {
        $effects = [];
        foreach (SpecShape::list($document, 'effects') as $row) {
            $effects[] = self::one($row);
        }

        return $effects;
    }

    /**
     * Один эффект.
     *
     * @param mixed $row Строка.
     *
     * @return StateEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function one(mixed $row): StateEffect
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('effects');
        }

        return self::byType(SpecShape::string($row, 'type'), $row);
    }

    /**
     * Ветка эффекта.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     *
     * @return StateEffect Эффект.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    private static function byType(string $type, array $row): StateEffect
    {
        return match ($type) {
            'characteristic_modify' => new CharacteristicModifyEffect(
                SpecShape::string($row, 'characteristic_code'),
                SpecShape::int($row, 'amount'),
                SpecShape::bool($row, 'per_unit'),
            ),
            'damage_over_time' => self::damage($row),
            'resource_limit_modify' => new ResourceLimitModifyEffect(
                SpecShape::string($row, 'resource_code'),
                SpecShape::int($row, 'amount'),
                SpecShape::bool($row, 'per_unit'),
            ),
            'resource_limit_set' => new ResourceLimitSetEffect(
                SpecShape::string($row, 'resource_code'),
                SpecShape::int($row, 'value'),
            ),
            'check_advantage' => self::check($row),
            default => throw new RuleSpecShapeException('effects'),
        };
    }

    /**
     * Урон со временем.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return DamageOverTimeEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function damage(array $row): DamageOverTimeEffect
    {
        $damage = SpecShape::object($row, 'damage') ?? [];
        $period = SpecShape::object($row, 'periodicity');
        $kind = SpecShape::string($damage, 'kind');

        return new DamageOverTimeEffect(
            $kind,
            $kind === 'fixed' ? SpecShape::int($damage, 'amount') : 0,
            SpecShape::optionalString($row, 'damage_type_code'),
            $period === null ? null : SpecShape::int($period, 'value'),
            $period === null ? null : SpecShape::string($period, 'step'),
            self::decay($row),
        );
    }

    /**
     * Помеха проверки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return CheckAdvantageEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function check(array $row): CheckAdvantageEffect
    {
        return new CheckAdvantageEffect(
            SpecShape::int($row, 'amount'),
            SpecShape::bool($row, 'per_unit'),
            SpecShape::optionalString($row, 'scale'),
            SpecShape::bool($row, 'includes_hit'),
            SpecShape::stringList($row, 'characteristic_codes'),
            SpecShape::stringList($row, 'check_codes'),
            SpecShape::optionalString($row, 'source_code'),
            SpecShape::optionalInt($row, 'max_abs'),
        );
    }

    /**
     * Затухание.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return StateDecay|null Узел или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function decay(array $row): ?StateDecay
    {
        $decay = SpecShape::object($row, 'decay');
        if ($decay === null) {
            return null;
        }

        $kind = SpecShape::string($decay, 'kind');

        return match ($kind) {
            'fixed' => new FixedDecay(SpecShape::int($decay, 'value')),
            'dimensional' => new DimensionalDecay(new DimensionalNumber(SpecShape::int($decay, 'base'), SpecShape::int($decay, 'size'))),
            'characteristic' => new CharacteristicDecay(
                SpecShape::string($decay, 'characteristic_code'),
                SpecShape::int($decay, 'modifier'),
            ),
            'check' => new CheckDecay(SpecShape::string($decay, 'characteristic_code')),
            default => throw new RuleSpecShapeException('decay'),
        };
    }
}
