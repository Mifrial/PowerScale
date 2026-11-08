<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;
use Mifrial\Roleplay\Character\Dto\ResourceLimitBonus;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MagicPathGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use Mifrial\Roleplay\Rule\Value\RequirementNumbers;
use Mifrial\Roleplay\Rule\Value\RequirementTraits;
use Mifrial\Roleplay\Rule\Value\RequirementUnmodelled;
use Mifrial\Roleplay\Rule\Value\RequirementValues;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;

/**
 * Собирает факты листа для проверки требований способностей.
 */
final class CharacterRequirementFacts
{
    private readonly CharacterChoiceAssembler $assembler;

    private readonly CharacterResourceArithmetic $arithmetic;

    private readonly CharacterAbilityGrantReader $grants;

    private readonly CharacterStudyPaths $paths;

    /**
     * Собирает помощники.
     *
     * @return void
     */
    public function __construct()
    {
        $this->assembler = new CharacterChoiceAssembler();
        $this->arithmetic = new CharacterResourceArithmetic();
        $this->grants = new CharacterAbilityGrantReader(new CharacterDonorGrants(), new CharacterRaceAbilityCodes());
        $this->paths = new CharacterStudyPaths();
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
            $this->traits($slice, $choices, $characteristics),
            new RequirementUnmodelled(
                null,
                $this->magicPaths($slice, $choices, $levels),
                $this->paths->experience($slice, $choices, $levels),
            ),
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
     * Пути из активных грантов magic_path с включениями.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $levels Уровни.
     *
     * @return array<int, string> Коды путей.
     */
    private function magicPaths(CharacterRuleSlice $slice, CharacterChoices $choices, array $levels): array
    {
        $paths = [];
        foreach ($this->grants->grants($slice, $choices, $levels) as $row) {
            $grant = $row->getGrant();
            if (!$grant instanceof MagicPathGrant) {
                continue;
            }

            foreach ($this->paths->covered($slice, $grant->getPathCode()) as $code) {
                if ($this->paths->isPath($slice, $code)) {
                    $paths[$code] = $code;
                }
            }
        }

        return array_values($paths);
    }

    /**
     * Признаки персонажа, способностей и семей оружия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, DimensionalNumber> $characteristics Живые характеристики.
     *
     * @return RequirementTraits Признаки.
     */
    public function traits(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $characteristics = [],
    ): RequirementTraits {
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
            $this->weaponMasteryValues($slice, $choices, $characteristics),
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

            $source = $row['sourceRuleCode'] ?? null;
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
            $base = CharacterAbilityBase::of($spec);
            if ($base === null || $base->getDomainRef() !== 'weapon-family') {
                continue;
            }

            $familyCode = $ability->getDomainCode() !== '' ? $ability->getDomainCode() : $ability->getDomain();
            $levels[$familyCode] = max($levels[$familyCode] ?? 0, $ability->getLevel());
        }

        return $levels;
    }

    /**
     * Лучшее мастерство каждой семьи оружия по её профилям.
     *
     * Мастерство — характеристика профиля, сдвинутая уровнем владения семьёй.
     * Семья без купленного уровня тоже получает базовое мастерство: требование
     * не должно зависеть от того, надето ли оружие прямо сейчас.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, DimensionalNumber> $characteristics Живые характеристики.
     *
     * @return array<string, DimensionalNumber> Семья → мастерство.
     */
    private function weaponMasteryValues(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $characteristics,
    ): array {
        $profiles = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            $value = $characteristics[$rule->getCode()] ?? null;
            if (!$spec instanceof CharacteristicSpec || !$value instanceof DimensionalNumber) {
                continue;
            }

            if ($spec->getWeaponMasteryProfiles() === []) {
                continue;
            }

            $profiles[] = $value instanceof CharacteristicNumber
                ? $value
                : new CharacteristicNumber($value->getBase(), $value->getSize());
        }

        if ($profiles === []) {
            return [];
        }

        $levels = $this->weaponMasteryLevels($slice, $choices);
        $values = [];
        foreach ($this->weaponFamiliesByKeyword($slice) as $family => $_) {
            $level = $levels[$family] ?? 0;
            $best = null;
            foreach ($profiles as $profile) {
                $mastery = $profile->modify($level);
                if ($best === null || $mastery->compare($best) > 0) {
                    $best = $mastery;
                }
            }

            $values[$family] = $best;
        }

        return $values;
    }
}
