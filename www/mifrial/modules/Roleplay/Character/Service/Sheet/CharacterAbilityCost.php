<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ArrayZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\AutomaticZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ProgressionZone;

/**
 * Цена карточки из первой платной зоны spec.
 *
 * Неподдержанная зона — null, а не 0: вызывающий решает, ошибка это или ноль.
 */
final class CharacterAbilityCost
{
    /**
     * Цена первого уровня.
     *
     * @param AbilityBase $base База.
     * @param array<string, int> $parameters Параметры выбора.
     *
     * @return int|null Цена или null.
     */
    public function firstLevel(AbilityBase $base, array $parameters): ?int
    {
        $zone = $this->pricedZone($base);

        return $zone === null ? null : $this->zoneCost($zone, 1, $parameters);
    }

    /**
     * Цена до взятого уровня включительно.
     *
     * @param AbilityBase $base База.
     * @param int $level Уровень.
     * @param array<string, int> $parameters Параметры выбора.
     *
     * @return int|null Цена или null.
     */
    public function toLevel(AbilityBase $base, int $level, array $parameters): ?int
    {
        $zone = $this->pricedZone($base);

        return $zone === null ? null : $this->zoneCost($zone, $level, $parameters);
    }

    /**
     * Первая не автоматическая зона.
     *
     * @param AbilityBase $base База.
     *
     * @return AbilityZone|null Зона или null.
     */
    private function pricedZone(AbilityBase $base): ?AbilityZone
    {
        foreach ($base->getZones() as $zone) {
            if ($zone instanceof AutomaticZone) {
                continue;
            }

            return $zone;
        }

        return null;
    }

    /**
     * Цена зоны.
     *
     * @param AbilityZone $zone Зона.
     * @param int $level Уровень.
     * @param array<string, int> $parameters Параметры.
     *
     * @return int|null Цена или null для неподдержанной зоны.
     */
    private function zoneCost(AbilityZone $zone, int $level, array $parameters): ?int
    {
        return match (true) {
            $zone instanceof ArrayZone => $this->arrayCost($zone, $level),
            $zone instanceof ProgressionZone => $this->progressionCost($zone, $level),
            $zone instanceof ParameterZone => ($parameters[$zone->getParameterCode()] ?? 1) * $zone->getPerUnit(),
            default => null,
        };
    }

    /**
     * Цена массива цен до уровня.
     *
     * @param ArrayZone $zone Зона.
     * @param int $level Уровень.
     *
     * @return int|null Цена или null, если уровня нет в массиве.
     */
    private function arrayCost(ArrayZone $zone, int $level): ?int
    {
        $prices = array_slice($zone->getLevelsCost(), 0, $level);
        if ($prices === []) {
            return null;
        }

        $sum = 0;
        foreach ($prices as $price) {
            $sum += $price;
        }

        return $sum;
    }

    /**
     * Цена прогрессии до уровня.
     *
     * @param ProgressionZone $zone Зона.
     * @param int $level Уровень.
     *
     * @return int Цена.
     */
    private function progressionCost(ProgressionZone $zone, int $level): int
    {
        $sum = 0;
        $steps = min($level, $zone->getMaxLevel());
        for ($index = 0; $index < $steps; $index++) {
            $sum += $zone->getBaseCost() + $index * $zone->getStep();
        }

        return $sum;
    }
}
