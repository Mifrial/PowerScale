<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\AndRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CharacteristicValueRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\OrRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\ResourceLimitRequirement;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Эффективный уровень способности по требованиям.
 */
final class CharacterAbilityActivity
{
    /**
     * Уровни после проверки требований.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $chosen Купленные уровни.
     * @param array<string, DimensionalNumber> $characteristics Живые характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты ресурсов.
     *
     * @return array<string, int> Код → уровень.
     */
    public function effectiveLevels(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $chosen,
        array $characteristics,
        array $resourceLimits,
    ): array {
        $levels = $chosen;
        foreach ($choices->getAbilities() as $ability) {
            $code = $ability->getRuleCode();
            $wanted = $levels[$code] ?? 0;
            $levels[$code] = $this->capped($slice, $code, $wanted, $levels, $characteristics, $resourceLimits);
        }

        return $levels;
    }

    /**
     * Максимальный уровень, на котором требования выполнены.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Способность.
     * @param int $wanted Заявленный уровень.
     * @param array<string, int> $levels Текущие уровни.
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты.
     *
     * @return int Уровень.
     */
    private function capped(
        CharacterRuleSlice $slice,
        string $code,
        int $wanted,
        array $levels,
        array $characteristics,
        array $resourceLimits,
    ): int {
        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof AbilitySpec || $wanted < 1) {
            return max(0, $wanted);
        }

        $active = 0;
        for ($level = 1; $level <= $wanted; $level++) {
            if (!$this->blockPasses($spec, $level, $levels, $characteristics, $resourceLimits)) {
                break;
            }

            $active = $level;
        }

        return $active;
    }

    /**
     * Требования spec, если есть база.
     *
     * @param AbilitySpec $spec Способность.
     *
     * @return array<int, array{level: int, requirements: array<int, AbilityRequirement>}> Блоки.
     */
    private function requirementBlocks(AbilitySpec $spec): array
    {
        if (
            $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityActionSpec
            || $spec instanceof AbilitySpellSpec
            || $spec instanceof AbilityProcessSpec
        ) {
            return $spec->getBase()->getRequirements();
        }

        return [];
    }

    /**
     * Требования блока уровня.
     *
     * @param AbilitySpec $spec Способность.
     * @param int $level Уровень.
     * @param array<string, int> $levels Уровни.
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты.
     *
     * @return bool true, если блок активен.
     */
    private function blockPasses(
        AbilitySpec $spec,
        int $level,
        array $levels,
        array $characteristics,
        array $resourceLimits,
    ): bool {
        foreach ($this->requirementBlocks($spec) as $block) {
            if ($block['level'] !== $level) {
                continue;
            }

            foreach ($block['requirements'] as $requirement) {
                if (!$this->passes($requirement, $levels, $characteristics, $resourceLimits)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Одно требование. Неизвестные типы не гасят уровень.
     *
     * @param AbilityRequirement $requirement Требование.
     * @param array<string, int> $levels Уровни.
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты.
     *
     * @return bool true, если выполнено.
     */
    private function passes(
        AbilityRequirement $requirement,
        array $levels,
        array $characteristics,
        array $resourceLimits,
    ): bool {
        if ($requirement instanceof AndRequirement) {
            foreach ($requirement->getRequirements() as $child) {
                if (!$this->passes($child, $levels, $characteristics, $resourceLimits)) {
                    return false;
                }
            }

            return true;
        }

        if ($requirement instanceof OrRequirement) {
            foreach ($requirement->getRequirements() as $child) {
                if ($this->passes($child, $levels, $characteristics, $resourceLimits)) {
                    return true;
                }
            }

            return $requirement->getRequirements() === [];
        }

        if ($requirement instanceof CharacteristicValueRequirement) {
            $value = $characteristics[$requirement->getCharacteristicCode()] ?? null;

            return $value !== null && $value->compare($requirement->getMin()) >= 0;
        }

        if ($requirement instanceof HasAbilityRequirement) {
            $have = $levels[$requirement->getAbilityCode()] ?? 0;
            $min = $requirement->getMinLevel() ?? 1;

            return $have >= $min;
        }

        if ($requirement instanceof ResourceLimitRequirement) {
            return $this->resourcePasses($requirement, $resourceLimits);
        }

        return true;
    }

    /**
     * Лимит ресурса не ниже минимума.
     *
     * @param ResourceLimitRequirement $requirement Требование.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты.
     *
     * @return bool true, если выполнено.
     */
    private function resourcePasses(ResourceLimitRequirement $requirement, array $resourceLimits): bool
    {
        $have = $resourceLimits[$requirement->getResourceCode()] ?? null;
        $min = $requirement->getMin();
        if ($have === null || $min === null) {
            return $min === null;
        }

        if (is_int($have) && is_int($min)) {
            return $have >= $min;
        }

        if ($have instanceof DimensionalNumber && $min instanceof DimensionalNumber) {
            return $have->compare($min) >= 0;
        }

        return false;
    }
}
