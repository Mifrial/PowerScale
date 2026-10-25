<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Dto\ResourceLimitBonus;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceGrantReader;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceLimits;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Rule\Dto\Spec\State\ResourceLimitModifyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\ResourceLimitSetEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Полный пересчёт производных ключей sheet.
 */
final class CharacterSheetLive
{
    private readonly CharacterChoiceAssembler $choiceAssembler;

    private readonly CharacterLiveCharacteristics $characteristics;

    private readonly CharacterAbilityActivity $activity;

    private readonly CharacterResourceLimits $resourceLimits;

    private readonly CharacterResourceStorage $resourceStorage;

    private readonly CharacterResourceArithmetic $arithmetic;

    private readonly CharacterWeaponFamilyMastery $weaponFamilies;

    private readonly CharacterDerivedLevels $derivedLevels;

    /**
     * Собирает части расчёта.
     *
     * @param ICharacterSheets $sheets Валидатор C4.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterSheets $sheets,
    ) {
        $evaluations = new FormulaEvaluations();
        $donorGrants = new CharacterDonorGrants();
        $equipped = new CharacterEquippedLive();
        $this->choiceAssembler = new CharacterChoiceAssembler();
        $this->characteristics = new CharacterLiveCharacteristics(
            $evaluations,
            $donorGrants,
            new CharacterRaceAbilityCodes(),
            $equipped,
        );
        $this->activity = new CharacterAbilityActivity();
        $this->resourceLimits = new CharacterResourceLimits(
            $evaluations,
            new CharacterResourceGrantReader($donorGrants),
            new CharacterResourceArithmetic(),
        );
        $this->resourceStorage = new CharacterResourceStorage();
        $this->arithmetic = new CharacterResourceArithmetic();
        $this->weaponFamilies = new CharacterWeaponFamilyMastery($equipped);
        $this->derivedLevels = new CharacterDerivedLevels();
    }

    /**
     * Дописывает живые характеристики, уровни и лимиты ресурсов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Документ sheet.
     *
     * @return array<string, mixed> Sheet.
     */
    public function compute(CharacterRuleSlice $slice, array $choices, array $sheet): array
    {
        $model = $this->model($choices);
        $validation = $this->sheets->validate($slice, $model);
        $chosen = $validation->getAbilityLevels();
        $requirementLevels = $this->withDerived($slice, $model, $chosen);
        $levels = $requirementLevels;
        $live = [];
        $resourceLimits = [];
        for ($pass = 0; $pass < 3; $pass++) {
            $rows = $this->characteristics->compute($slice, $model, $validation, $sheet, $levels);
            $live = $this->indexCharacteristics($rows);
            $levels = $this->withDerived(
                $slice,
                $model,
                $this->activity->effectiveLevels($slice, $model, $chosen, $live, $resourceLimits, $requirementLevels),
            );
            $resourceRows = $this->resources($slice, $validation, $model, $sheet, $live);
            $resourceLimits = $this->indexLimits($resourceRows);
            $sheet['characteristics'] = $rows;
            $sheet['resources'] = $this->resourceStorage->serializeRows($resourceRows);
            $sheet['abilityLevels'] = $levels;
        }

        $sheet['senses'] = $this->characteristics->activeSenses($slice, $model, $validation, $levels);
        $sheet['weaponFamilyMastery'] = $this->weaponFamilies->compute($slice, $model, $live);
        $sheet['racialAbilityCodes'] = $validation->getRacialAbilityCodes();
        $sheet['osSurchargeTotal'] = $validation->getOsSurchargeTotal();
        $sheet['equippedModifiers'] = $sheet['equippedModifiers'] ?? [];
        $sheet['coveredPaths'] = $validation->getCoveredPaths();
        $sheet['characteristicPurchases'] = $sheet['characteristicPurchases'] ?? [];
        $sheet['characteristicPurchaseOs'] = $sheet['characteristicPurchaseOs'] ?? 0;
        $sheet['active'] = $validation->isActive();
        if (isset($choices['money']) && is_int($choices['money'])) {
            $sheet['money'] = $choices['money'];
        }

        return $sheet;
    }

    /**
     * Поднимает уровни до производных по порогам опыта.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $model Выборы.
     * @param array<string, int> $levels Уровни.
     *
     * @return array<string, int> Уровни.
     */
    private function withDerived(CharacterRuleSlice $slice, CharacterChoices $model, array $levels): array
    {
        foreach ($this->derivedLevels->levels($slice, $model) as $code => $level) {
            $levels[$code] = max($levels[$code] ?? 0, $level);
        }

        return $levels;
    }

    /**
     * Выборы из документа. Неполный документ — пустая раса.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices Выборы.
     */
    public function model(array $choices): CharacterChoices
    {
        return $this->choiceAssembler->assemble(
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
     * Строки ресурсов с живым контекстом и эффектами состояний.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterValidation $validation Снимок.
     * @param CharacterChoices $model Выборы.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<string, \Mifrial\Roleplay\Rule\Value\CharacteristicNumber> $live Характеристики.
     *
     * @return array<int, ResourceRow> Строки.
     */
    private function resources(
        CharacterRuleSlice $slice,
        CharacterValidation $validation,
        CharacterChoices $model,
        array $sheet,
        array $live,
    ): array {
        $previous = [];
        if (isset($sheet['resources']) && is_array($sheet['resources'])) {
            $previous = $this->resourceStorage->parseRows($sheet['resources'], $slice);
        }

        $rows = $this->resourceLimits->buildRows($slice, $validation, $model, $previous, $live);

        return $this->stateResourceLimits($slice, $sheet, $rows);
    }

    /**
     * Эффекты состояний на лимит ресурса.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<int, ResourceRow> $rows Строки.
     *
     * @return array<int, ResourceRow> Строки.
     */
    private function stateResourceLimits(CharacterRuleSlice $slice, array $sheet, array $rows): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states) || $states === []) {
            return $rows;
        }

        $next = [];
        foreach ($rows as $row) {
            $base = $row->getBase();
            $bonuses = $row->getBonuses();
            foreach ($states as $state) {
                if (!is_array($state)) {
                    continue;
                }

                $bonuses = $this->applyStateBonuses(
                    $slice,
                    $state,
                    $row->getRuleCode(),
                    $base,
                    $bonuses,
                );
            }

            if ($base === null) {
                $next[] = $row;
                continue;
            }

            $limit = $this->arithmetic->effective($base, $bonuses);
            $current = $this->arithmetic->clamp($row->getCurrent(), $limit);
            $next[] = new ResourceRow($row->getRuleCode(), $current, $base, $bonuses);
        }

        return $next;
    }

    /**
     * Один эффект состояния на лимит.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $state Строка состояния.
     * @param string $resourceCode Ресурс.
     * @param int|DimensionalNumber|null $base База.
     * @param array<int, ResourceLimitBonus> $bonuses Вклады.
     *
     * @return array<int, ResourceLimitBonus> Вклады.
     */
    private function applyStateBonuses(
        CharacterRuleSlice $slice,
        array $state,
        string $resourceCode,
        int|DimensionalNumber|null $base,
        array $bonuses,
    ): array {
        $code = $state['stateRuleCode'] ?? null;
        $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
        if (!$spec instanceof StateSpec) {
            return $bonuses;
        }

        $units = is_int($state['value'] ?? null) ? $state['value'] : 1;
        foreach ($spec->getEffects() as $effect) {
            if ($effect instanceof ResourceLimitSetEffect && $effect->getResourceCode() === $resourceCode) {
                $bonuses = [$this->setBonus($code, $base, $effect->getValue())];
            }

            if ($effect instanceof ResourceLimitModifyEffect && $effect->getResourceCode() === $resourceCode) {
                $delta = $effect->getAmount() * ($effect->isPerUnit() ? $units : 1);
                $bonuses[] = new ResourceLimitBonus(is_string($code) ? $code : null, $delta);
            }
        }

        return $bonuses;
    }

    /**
     * Вклад, который приводит базу к абсолютному лимиту состояния.
     *
     * @param mixed $source Код состояния.
     * @param int|DimensionalNumber|null $base База.
     * @param int $value Новый лимит.
     *
     * @return ResourceLimitBonus Вклад.
     */
    private function setBonus(
        mixed $source,
        int|DimensionalNumber|null $base,
        int $value,
    ): ResourceLimitBonus {
        $code = is_string($source) ? $source : null;
        $from = is_int($base) ? $base : ($base instanceof DimensionalNumber ? $base->getBase() : 0);

        return new ResourceLimitBonus($code, $value - $from);
    }

    /**
     * Индекс живых характеристик.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     *
     * @return array<string, CharacteristicNumber> Код → значение.
     */
    private function indexCharacteristics(array $rows): array
    {
        $live = [];
        foreach ($rows as $row) {
            $code = $row['characteristicCode'] ?? null;
            $value = $row['value'] ?? null;
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
     * Индекс лимитов ресурсов.
     *
     * @param array<int, ResourceRow> $rows Строки.
     *
     * @return array<string, int|DimensionalNumber> Код → лимит.
     */
    private function indexLimits(array $rows): array
    {
        $limits = [];
        foreach ($rows as $row) {
            $base = $row->getBase();
            if ($base !== null) {
                $limits[$row->getRuleCode()] = $this->arithmetic->effective($base, $row->getBonuses());
            }
        }

        return $limits;
    }
}
