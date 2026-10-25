<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use Mifrial\Roleplay\Rule\Value\RequirementNumbers;
use Mifrial\Roleplay\Rule\Value\RequirementTraits;
use Mifrial\Roleplay\Rule\Value\RequirementUnmodelled;
use Mifrial\Roleplay\Rule\Value\RequirementValues;

/**
 * Собирает факты листа для проверки требований способностей.
 */
final class CharacterRequirementFacts
{
    /**
     * Факты из модели выборов, уровней и посчитанного листа.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $levels Уровни для требований.
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты ресурсов.
     *
     * @return RequirementValues Факты.
     */
    public function forModel(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $levels,
        array $characteristics,
        array $resourceLimits,
    ): RequirementValues {
        return new RequirementValues(
            new RequirementNumbers($levels, $characteristics, $resourceLimits),
            $this->traits($slice, $choices),
            new RequirementUnmodelled(),
        );
    }

    /**
     * Признаки персонажа, способностей и семей оружия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return RequirementTraits Признаки.
     */
    public function traits(CharacterRuleSlice $slice, CharacterChoices $choices): RequirementTraits
    {
        $keywords = [];
        $abilityKeywords = [];
        $raceCode = $choices->getRaceCode();
        if ($raceCode !== null) {
            $race = $slice->findLive($raceCode);
            if ($race !== null) {
                $keywords = [...$keywords, ...$race->getKeywordCodes()];
            }
        }

        foreach ($choices->getAbilities() as $ability) {
            $rule = $slice->findLive($ability->getRuleCode());
            if ($rule === null) {
                continue;
            }

            $abilityKeywords[$ability->getRuleCode()] = $rule->getKeywordCodes();
            $keywords = [...$keywords, ...$rule->getKeywordCodes()];
        }

        return new RequirementTraits(
            $abilityKeywords,
            array_values(array_unique($keywords)),
            $this->weaponFamiliesByKeyword($slice),
            $this->weaponMasteryLevels($slice, $choices),
        );
    }

    /**
     * Семьи оружия с признаками их правил-предметов.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, array<int, string>> Семья → признаки.
     */
    private function weaponFamiliesByKeyword(CharacterRuleSlice $slice): array
    {
        $families = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof ItemSpec || $spec->getWeapon() === null) {
                continue;
            }

            $familyCode = $spec->getProficiencyFamilyCode();
            if ($familyCode === null || $familyCode === '') {
                continue;
            }

            $families[$familyCode] = $rule->getKeywordCodes();
        }

        return $families;
    }

    /**
     * Купленные уровни владения семьями оружия без фильтра надетого.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<string, int> Семья → уровень.
     */
    private function weaponMasteryLevels(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $levels = [];
        foreach ($choices->getAbilities() as $ability) {
            $rule = $slice->findLive($ability->getRuleCode());
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            $base = $this->abilityBase($spec);
            if ($base === null || $base->getDomainRef() !== 'weapon-family') {
                continue;
            }

            $familyCode = $ability->getDomainCode() !== '' ? $ability->getDomainCode() : $ability->getDomain();
            $levels[$familyCode] = max($levels[$familyCode] ?? 0, $ability->getLevel());
        }

        return $levels;
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
