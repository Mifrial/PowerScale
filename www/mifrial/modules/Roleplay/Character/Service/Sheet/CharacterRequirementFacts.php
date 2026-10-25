<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceLimitBonus;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
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
    private readonly CharacterChoiceAssembler $assembler;

    private readonly CharacterResourceArithmetic $arithmetic;

    /**
     * Собирает помощники.
     *
     * @return void
     */
    public function __construct()
    {
        $this->assembler = new CharacterChoiceAssembler();
        $this->arithmetic = new CharacterResourceArithmetic();
    }

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
     * Факты из документов choices и посчитанного sheet.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Посчитанный лист.
     *
     * @return RequirementValues Факты.
     */
    public function forDocuments(CharacterRuleSlice $slice, array $choices, array $sheet): RequirementValues
    {
        return $this->forModel(
            $slice,
            $this->model($choices),
            $this->levelsOf($sheet),
            $this->characteristicsOf($sheet),
            $this->resourceLimitsOf($sheet),
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
     * Выборы из документа.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices Выборы.
     */
    private function model(array $choices): CharacterChoices
    {
        return $this->assembler->assemble(
            is_string($choices['name'] ?? null) ? $choices['name'] : '',
            is_string($choices['raceCode'] ?? null) ? $choices['raceCode'] : '',
            is_array($choices['abilities'] ?? null) ? $choices['abilities'] : [],
            is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [],
            is_array($choices['characteristicPurchases'] ?? null) ? $choices['characteristicPurchases'] : [],
            is_array($choices['customRules'] ?? null) ? $choices['customRules'] : [],
            is_bool($choices['active'] ?? null) ? $choices['active'] : null,
        );
    }

    /**
     * Уровни способностей из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, int> Код → уровень.
     */
    private function levelsOf(array $sheet): array
    {
        $levels = [];
        foreach (is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [] as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $levels[$code] = $level;
            }
        }

        return $levels;
    }

    /**
     * Живые характеристики из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, DimensionalNumber> Код → значение.
     */
    private function characteristicsOf(array $sheet): array
    {
        $live = [];
        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            $code = is_array($row) ? ($row['characteristicCode'] ?? null) : null;
            $value = is_array($row) ? ($row['value'] ?? null) : null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            $size = is_array($value) ? ($value['size'] ?? null) : null;
            if (!is_string($code) || !is_int($base) || !is_int($size)) {
                continue;
            }

            $live[$code] = new CharacteristicNumber($base, $size);
        }

        return $live;
    }

    /**
     * Эффективные лимиты ресурсов из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, int|DimensionalNumber> Код → лимит.
     */
    private function resourceLimitsOf(array $sheet): array
    {
        $limits = [];
        foreach (is_array($sheet['resources'] ?? null) ? $sheet['resources'] : [] as $row) {
            $code = is_array($row) ? ($row['ruleCode'] ?? null) : null;
            $base = is_array($row) ? $this->limitBase($row['base'] ?? null) : null;
            if (!is_string($code) || $base === null) {
                continue;
            }

            $bonuses = is_array($row['bonuses'] ?? null) ? $row['bonuses'] : [];
            $limits[$code] = $this->arithmetic->effective($base, $this->bonusesOf($bonuses));
        }

        return $limits;
    }

    /**
     * База лимита ресурса.
     *
     * @param mixed $base Целое или пара.
     *
     * @return int|DimensionalNumber|null Лимит или null.
     */
    private function limitBase(mixed $base): int|DimensionalNumber|null
    {
        if (is_int($base)) {
            return $base;
        }

        if (!is_array($base)) {
            return null;
        }

        $value = $base['base'] ?? null;
        $size = $base['size'] ?? null;

        return is_int($value) && is_int($size) ? new DimensionalNumber($value, $size) : null;
    }

    /**
     * Вклады лимита из листа.
     *
     * @param array<int, mixed> $rows Строки.
     *
     * @return array<int, ResourceLimitBonus> Вклады.
     */
    private function bonusesOf(array $rows): array
    {
        $bonuses = [];
        foreach ($rows as $row) {
            $delta = is_array($row) ? ($row['delta'] ?? null) : null;
            if (!is_int($delta)) {
                continue;
            }

            $source = is_array($row) ? ($row['sourceRuleCode'] ?? null) : null;
            $bonuses[] = new ResourceLimitBonus(is_string($source) ? $source : null, $delta);
        }

        return $bonuses;
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
