<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\BlockOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\DefenseOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\DurabilityOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ItemModifierOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MaxAgilityOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinStrengthOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\StrengthPenaltyOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\WeightOp;

/**
 * Схлопывание числовых вкладов операций: источник, затем сумма источников.
 */
final class ItemModifierOperationNumbers
{
    /**
     * Схлопывает вклады по полю и источнику.
     *
     * @param array<int, array<string, int|float|string|null>> $entries Вклады.
     *
     * @return array<string, array{factor: float, delta: int, size: int}> Итог поля.
     */
    public static function collapse(array $entries): array
    {
        $collapsed = [];
        foreach (self::byTarget($entries) as $target => $group) {
            $collapsed[$target] = self::collapseGroup($group);
        }

        return $collapsed;
    }

    /**
     * Сильнейший бонус и сильнейший штраф одного источника.
     *
     * @param array<int, array{factor: float|null, delta: int, size: int}> $group Вклады.
     *
     * @return array{factor: float, delta: int, size: int} Итог.
     */
    public static function collapseSource(array $group): array
    {
        $bonus = null;
        $penalty = null;
        $plus = null;
        $minus = null;
        $sizePlus = null;
        $sizeMinus = null;
        foreach ($group as $entry) {
            $bonus = self::strongerBonus($bonus, $entry['factor']);
            $penalty = self::strongerPenalty($penalty, $entry['factor']);
            $plus = self::strongerPlus($plus, $entry['delta']);
            $minus = self::strongerMinus($minus, $entry['delta']);
            $sizePlus = self::strongerPlus($sizePlus, $entry['size']);
            $sizeMinus = self::strongerMinus($sizeMinus, $entry['size']);
        }

        return [
            'factor' => ($bonus ?? 1.0) * ($penalty ?? 1.0),
            'delta' => ($plus ?? 0) + ($minus ?? 0),
            'size' => ($sizePlus ?? 0) + ($sizeMinus ?? 0),
        ];
    }

    /**
     * Один вклад ветки.
     *
     * @param string $target Поле.
     * @param string $source Ключ источника.
     * @param ItemModifierOp $op Ветка.
     *
     * @return array{target: string, source: string, factor: float|null, delta: int, size: int} Вклад.
     */
    public static function entry(string $target, string $source, ItemModifierOp $op): array
    {
        return [
            'target' => $target,
            'source' => $source,
            'factor' => self::factor($op),
            'delta' => self::delta($op),
            'size' => self::size($op),
        ];
    }

    /**
     * Группы по полю.
     *
     * @param array<int, array<string, int|float|string|null>> $entries Вклады.
     *
     * @return array<string, array<int, array{
     *     source: string,
     *     factor: float|null,
     *     delta: int,
     *     size: int
     * }>> Группы.
     */
    private static function byTarget(array $entries): array
    {
        $byTarget = [];
        foreach ($entries as $entry) {
            $byTarget[$entry['target']][] = $entry;
        }

        return $byTarget;
    }

    /**
     * Итог одного поля: внутри источника, затем между источниками.
     *
     * @param array<int, array{source: string, factor: float|null, delta: int, size: int}> $group Вклады поля.
     *
     * @return array{factor: float, delta: int, size: int} Итог.
     */
    private static function collapseGroup(array $group): array
    {
        $factor = 1.0;
        $delta = 0;
        $size = 0;
        foreach (self::bySource($group) as $sourceGroup) {
            $one = self::collapseSource($sourceGroup);
            $factor *= $one['factor'];
            $delta += $one['delta'];
            $size += $one['size'];
        }

        return ['factor' => $factor, 'delta' => $delta, 'size' => $size];
    }

    /**
     * Группы одного поля по источнику.
     *
     * @param array<int, array{source: string, factor: float|null, delta: int, size: int}> $group Вклады поля.
     *
     * @return array<string, array<int, array{factor: float|null, delta: int, size: int}>> Группы.
     */
    private static function bySource(array $group): array
    {
        $bySource = [];
        foreach ($group as $entry) {
            $bySource[$entry['source']][] = $entry;
        }

        return $bySource;
    }

    /**
     * Множитель ветки или null.
     *
     * @param ItemModifierOp $op Ветка.
     *
     * @return float|null Число или null.
     */
    private static function factor(ItemModifierOp $op): ?float
    {
        if ($op instanceof WeightOp || $op instanceof BlockOp || $op instanceof DefenseOp) {
            $factor = $op->getFactor();

            return $factor === null ? null : (float) $factor;
        }

        return null;
    }

    /**
     * Слагаемое ветки.
     *
     * @param ItemModifierOp $op Ветка.
     *
     * @return int Пункты.
     */
    private static function delta(ItemModifierOp $op): int
    {
        return match (true) {
            $op instanceof WeightOp => (int) ($op->getAddKg() ?? 0),
            $op instanceof BlockOp,
                $op instanceof DefenseOp,
                $op instanceof StrengthPenaltyOp => $op->getAdd() ?? 0,
            $op instanceof MinStrengthOp => $op->getDelta(),
            $op instanceof DurabilityOp, $op instanceof MaxAgilityOp => $op->getDelta() ?? 0,
            default => 0,
        };
    }

    /**
     * Сдвиг размера.
     *
     * @param ItemModifierOp $op Ветка.
     *
     * @return int Пункты.
     */
    private static function size(ItemModifierOp $op): int
    {
        $sized = $op instanceof BlockOp
            || $op instanceof DefenseOp
            || $op instanceof DurabilityOp
            || $op instanceof MaxAgilityOp;
        if ($sized) {
            return $op->getAddSize() ?? 0;
        }

        return 0;
    }

    /**
     * Наибольший множитель больше единицы.
     *
     * @param float|null $current Уже выбранный.
     * @param float|null $factor Кандидат.
     *
     * @return float|null Бонус или null.
     */
    private static function strongerBonus(?float $current, ?float $factor): ?float
    {
        if ($factor === null || $factor <= 1.0) {
            return $current;
        }

        return self::larger($current, $factor);
    }

    /**
     * Наименьший множитель меньше единицы.
     *
     * @param float|null $current Уже выбранный.
     * @param float|null $factor Кандидат.
     *
     * @return float|null Штраф или null.
     */
    private static function strongerPenalty(?float $current, ?float $factor): ?float
    {
        if ($factor === null || $factor >= 1.0) {
            return $current;
        }

        return self::smaller($current, $factor);
    }

    /**
     * Наибольшее положительное слагаемое.
     *
     * @param int|null $current Уже выбранное.
     * @param int $delta Кандидат.
     *
     * @return int|null Бонус или null.
     */
    private static function strongerPlus(?int $current, int $delta): ?int
    {
        if ($delta <= 0) {
            return $current;
        }

        return self::largerInt($current, $delta);
    }

    /**
     * Наименьшее отрицательное слагаемое.
     *
     * @param int|null $current Уже выбранное.
     * @param int $delta Кандидат.
     *
     * @return int|null Штраф или null.
     */
    private static function strongerMinus(?int $current, int $delta): ?int
    {
        if ($delta >= 0) {
            return $current;
        }

        return self::smallerInt($current, $delta);
    }

    /**
     * Большее из двух положительных множителей.
     *
     * @param float|null $current Уже выбранный.
     * @param float $factor Кандидат.
     *
     * @return float Бонус.
     */
    private static function larger(?float $current, float $factor): float
    {
        if ($current === null || $factor > $current) {
            return $factor;
        }

        return $current;
    }

    /**
     * Меньшее из двух штрафных множителей.
     *
     * @param float|null $current Уже выбранный.
     * @param float $factor Кандидат.
     *
     * @return float Штраф.
     */
    private static function smaller(?float $current, float $factor): float
    {
        if ($current === null || $factor < $current) {
            return $factor;
        }

        return $current;
    }

    /**
     * Большее положительное слагаемое.
     *
     * @param int|null $current Уже выбранное.
     * @param int $delta Кандидат.
     *
     * @return int Бонус.
     */
    private static function largerInt(?int $current, int $delta): int
    {
        if ($current === null || $delta > $current) {
            return $delta;
        }

        return $current;
    }

    /**
     * Меньшее отрицательное слагаемое.
     *
     * @param int|null $current Уже выбранное.
     * @param int $delta Кандидат.
     *
     * @return int Штраф.
     */
    private static function smallerInt(?int $current, int $delta): int
    {
        if ($current === null || $delta < $current) {
            return $delta;
        }

        return $current;
    }
}
