<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ArrayZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\AutomaticZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ProgressionZone;

/**
 * Производный уровень способности по сумме стоимостей с признаком опыта.
 */
final class CharacterDerivedLevels
{
    /**
     * Код → уровень. Нулевой уровень не пишется.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<string, int> Уровни.
     */
    public function levels(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $levels = [];
        foreach ($slice->getLiveRules() as $rule) {
            $base = $this->abilityBase($rule->isSpecBroken() ? null : $rule->getSpec());
            $derived = $base?->getDerivedLevel();
            if ($base === null || $derived === null) {
                continue;
            }

            $experience = $this->experience($slice, $choices, $derived->getSourceKeyword());
            $level = 0;
            foreach ($derived->getThresholds() as $threshold) {
                if ($experience >= $threshold) {
                    $level++;
                }
            }

            if ($level > 0) {
                $levels[$rule->getCode()] = $level;
            }
        }

        return $levels;
    }

    /**
     * Сумма цен взятых способностей с признаком.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param string $keyword Код признака.
     *
     * @return int Опыт.
     */
    private function experience(CharacterRuleSlice $slice, CharacterChoices $choices, string $keyword): int
    {
        $sum = 0;
        foreach ($choices->getAbilities() as $ability) {
            if (!$ability instanceof CharacterAbilityChoice || $ability->getLevel() < 1) {
                continue;
            }

            $rule = $slice->findLive($ability->getRuleCode());
            if ($rule === null || $rule->isSpecBroken() || !in_array($keyword, $rule->getKeywordCodes(), true)) {
                continue;
            }

            $base = $this->abilityBase($rule->getSpec());
            if ($base === null) {
                continue;
            }

            $sum += $this->cost($base, $ability->getLevel(), $ability->getParameters());
        }

        return $sum;
    }

    /**
     * Цена первой платной зоны до взятого уровня.
     *
     * @param AbilityBase $base База.
     * @param int $level Уровень.
     * @param array<string, int> $parameters Параметры.
     *
     * @return int Цена.
     */
    private function cost(AbilityBase $base, int $level, array $parameters): int
    {
        foreach ($base->getZones() as $zone) {
            if ($zone instanceof AutomaticZone) {
                continue;
            }

            return $this->zoneCost($zone, $level, $parameters);
        }

        return 0;
    }

    /**
     * Цена зоны.
     *
     * @param AbilityZone $zone Зона.
     * @param int $level Уровень.
     * @param array<string, int> $parameters Параметры.
     *
     * @return int Цена.
     */
    private function zoneCost(AbilityZone $zone, int $level, array $parameters): int
    {
        if ($zone instanceof ArrayZone) {
            $sum = 0;
            foreach (array_slice($zone->getLevelsCost(), 0, $level) as $price) {
                $sum += $price;
            }

            return $sum;
        }

        if ($zone instanceof ProgressionZone) {
            $sum = 0;
            $steps = min($level, $zone->getMaxLevel());
            for ($index = 0; $index < $steps; $index++) {
                $sum += $zone->getBaseCost() + $index * $zone->getStep();
            }

            return $sum;
        }

        if ($zone instanceof ParameterZone) {
            return ($parameters[$zone->getParameterCode()] ?? 1) * $zone->getPerUnit();
        }

        return 0;
    }

    /**
     * Общая база способности.
     *
     * @param mixed $spec Спека.
     *
     * @return AbilityBase|null База или null.
     */
    private function abilityBase(mixed $spec): ?AbilityBase
    {
        if (
            $spec instanceof AbilityActionSpec
            || $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityProcessSpec
            || $spec instanceof AbilitySpellSpec
        ) {
            return $spec->getBase();
        }

        return null;
    }
}
