<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterPurchasedCharacteristic;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityParameter;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SenseModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\SenseSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CharacteristicModifyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Живые характеристики: раса, гранты, агрегация, потолки предмета.
 */
final class CharacterLiveCharacteristics
{
    /**
     * Создаёт расчёт.
     *
     * @param IFormulaEvaluations $evaluations Формулы.
     * @param CharacterDonorGrants $donorGrants Гранты способностей.
     * @param CharacterRaceAbilityCodes $raceAbilityCodes Коды расы.
     * @param CharacterEquippedLive $equippedLive Надетое.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $evaluations,
        private readonly CharacterDonorGrants $donorGrants,
        private readonly CharacterRaceAbilityCodes $raceAbilityCodes,
        private readonly CharacterEquippedLive $equippedLive,
    ) {
    }

    /**
     * Считает характеристики одного прогона.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок C4.
     * @param array<string, mixed> $sheet Документ sheet.
     * @param array<string, int> $abilityLevels Эффективные уровни.
     *
     * @return array<int, array<string, mixed>> Строки characteristics.
     */
    public function compute(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
        array $sheet,
        array $abilityLevels,
    ): array {
        $bases = $this->bases($slice, $choices, $validation, $abilityLevels);
        $deltas = $this->deltas($slice, $choices, $sheet, $abilityLevels, $bases);
        $bases = $this->baseFrom($slice, $bases, $deltas);
        $values = $this->applied($bases, $deltas);
        $values = $this->derived($slice, $values);
        $values = $this->capped($slice, $choices, $values);

        return $this->rows($bases, $values, $deltas);
    }

    /**
     * Базы расы, закупок, грантов characteristic и automatic.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<string, CharacteristicNumber> Код → база.
     */
    private function bases(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
        array $abilityLevels,
    ): array {
        $bases = $this->raceBases($slice, $choices, $validation);
        foreach ($this->abilityGrants($slice, $choices, $abilityLevels) as $grant) {
            $body = $grant->getGrant();
            if ($body instanceof CharacteristicGrant) {
                $bases[$body->getCharacteristicCode()] = $body->getValue();
            }
        }

        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if (!$spec instanceof CharacteristicSpec) {
                continue;
            }

            $automatic = $spec->getAutomatic();
            if ($automatic === false || isset($bases[$rule->getCode()])) {
                continue;
            }

            $bases[$rule->getCode()] = $automatic instanceof CharacteristicNumber
                ? $automatic
                : new CharacteristicNumber(3, 0);
        }

        return $bases;
    }

    /**
     * Базы расы: fixed или купленная ступень / минимум.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function raceBases(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
    ): array {
        $race = $slice->findLive((string) $choices->getRaceCode())?->getSpec();
        if (!$race instanceof RaceSpec) {
            return [];
        }

        $purchased = [];
        foreach ($validation->getPurchasedCharacteristics() as $row) {
            $purchased[$row->getCharacteristicCode()] = $row;
        }

        $bases = [];
        foreach ($race->getCharacteristics() as $row) {
            $code = $row->getCharacteristicCode();
            if ($row->getMode() === 'fixed') {
                $bases[$code] = $row->getBase();
                continue;
            }

            $bases[$code] = ($purchased[$code] ?? null) instanceof CharacterPurchasedCharacteristic
                ? $purchased[$code]->getValue()
                : $row->getBase();
        }

        return $bases;
    }

    /**
     * Гранты выбранных и расовых способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<int, \Mifrial\Roleplay\Character\Dto\CharacterDonorGrant> Гранты.
     */
    private function abilityGrants(CharacterRuleSlice $slice, CharacterChoices $choices, array $abilityLevels): array
    {
        $rows = [];
        foreach ($abilityLevels as $code => $level) {
            $rule = $slice->findLive($code);
            if ($rule !== null) {
                array_push($rows, ...$this->donorGrants->getForLevel($rule, $code, $level));
            }
        }

        foreach ($this->raceAbilityCodes->getCodes($slice, $choices->getRaceCode()) as $code) {
            if (isset($abilityLevels[$code])) {
                continue;
            }

            $rule = $slice->findLive($code);
            if ($rule !== null) {
                array_push($rows, ...$this->donorGrants->getAll($rule, $code));
            }
        }

        return $rows;
    }

    /**
     * Модификаторы по коду характеристики.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Дельты.
     */
    private function deltas(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $sheet,
        array $abilityLevels,
        array $bases,
    ): array {
        $raw = [];
        foreach ($this->abilityGrants($slice, $choices, $abilityLevels) as $grant) {
            $body = $grant->getGrant();
            if (!$body instanceof CharacteristicModifyGrant || $body->getCheckCodes() !== []) {
                continue;
            }

            $context = new FormulaContext($bases, $abilityLevels, $this->parametersOf($slice, $choices, $grant));
            $delta = $this->amountOf($body, $context);
            foreach ($this->penaltyTargets($slice, $body->getCharacteristicCode()) as $target) {
                $raw[$target][] = [
                    'source' => $body->getSourceCode(),
                    'delta' => $delta,
                ];
            }
        }

        $itemContext = new FormulaContext($bases, $abilityLevels);
        foreach ($this->equippedLive->equipped($slice, $choices) as $item) {
            foreach ($item['spec']->getGrants() as $grant) {
                if (!$grant instanceof CharacteristicModifyGrant || $grant->getCheckCodes() !== []) {
                    continue;
                }

                $delta = $this->amountOf($grant, $itemContext);
                foreach ($this->penaltyTargets($slice, $grant->getCharacteristicCode()) as $target) {
                    $raw[$target][] = [
                        'source' => $item['ruleCode'],
                        'delta' => $delta,
                    ];
                }
            }
        }

        foreach ($this->stateDeltas($slice, $sheet) as $code => $rows) {
            foreach ($rows as $row) {
                $raw[$code][] = $row;
            }
        }

        $sense = $this->bestSenseDelta($this->senseRows($slice, $choices, $abilityLevels, $bases));
        if ($sense !== null) {
            $raw['attention'][] = $sense;
        }

        return $this->aggregate($raw);
    }

    /**
     * Активные чувства листа: auto_add и выданные грантом, без статуса «отсутствует».
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function activeSenses(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
        array $abilityLevels,
    ): array {
        $rows = $this->senseRows(
            $slice,
            $choices,
            $abilityLevels,
            $this->bases($slice, $choices, $validation, $abilityLevels),
        );
        $active = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'absent') {
                continue;
            }

            $active[] = $row;
        }

        return $active;
    }

    /**
     * Лучшее не-absent чувство. Бонус 0 не даёт модификатора.
     *
     * @param array<int, array<string, mixed>> $rows Чувства.
     *
     * @return array{source: string, delta: int}|null Вклад в Внимательность.
     */
    private function bestSenseDelta(array $rows): ?array
    {
        $bestCode = null;
        $best = null;
        foreach ($rows as $row) {
            if ($row['status'] === 'absent' || !is_int($row['value']) || !is_string($row['ruleCode'])) {
                continue;
            }

            if ($best === null || $row['value'] > $best) {
                $best = $row['value'];
                $bestCode = $row['ruleCode'];
            }
        }

        if ($bestCode === null || $best === 0) {
            return null;
        }

        return ['source' => $bestCode, 'delta' => $best];
    }

    /**
     * Чувства с auto_add и чувства, на которые есть грант.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function senseRows(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
    ): array {
        $rows = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof SenseSpec && $spec->isAutoAdd()) {
                $rows[$rule->getCode()] = $this->emptySense($rule->getCode(), $spec);
            }
        }

        $ranks = ['precise' => 0, 'imprecise' => 1, 'vague' => 2, 'absent' => 3];
        $names = ['precise', 'imprecise', 'vague', 'absent'];
        foreach ($this->abilityGrants($slice, $choices, $abilityLevels) as $grant) {
            $body = $grant->getGrant();
            if (!$body instanceof SenseModifyGrant) {
                continue;
            }

            $rule = $slice->findLive($body->getSenseCode());
            $spec = $rule?->getSpec();
            if (!$spec instanceof SenseSpec) {
                continue;
            }

            if (!isset($rows[$body->getSenseCode()])) {
                $rows[$body->getSenseCode()] = $this->emptySense($body->getSenseCode(), $spec);
            }

            $context = new FormulaContext($bases, $abilityLevels, $this->parametersOf($slice, $choices, $grant));
            try {
                $delta = $this->evaluations->evaluateScalar($body->getAmount(), $context);
            } catch (RuleInvalidException) {
                continue;
            }

            $rows[$body->getSenseCode()]['value'] += $delta;
            $rows[$body->getSenseCode()]['modifiers'][] = [
                'sourceRuleCode' => $body->getSourceCode(),
                'delta' => $delta,
            ];
            $status = $body->getStatus();
            $current = $ranks[$rows[$body->getSenseCode()]['status']] ?? 0;
            if ($status !== null && ($ranks[$status] ?? -1) > $current) {
                $rows[$body->getSenseCode()]['status'] = $names[$ranks[$status]];
            }

            if ($body->getTreatAsGoodDownTo() !== null) {
                $rows[$body->getSenseCode()]['treatAsGoodDownTo'] = $body->getTreatAsGoodDownTo();
            }
        }

        return array_values($rows);
    }

    /**
     * Чувство без грантов.
     *
     * @param string $code Код.
     * @param SenseSpec $spec Спека.
     *
     * @return array<string, mixed> Строка.
     */
    private function emptySense(string $code, SenseSpec $spec): array
    {
        $radius = $spec->getRadius();

        return [
            'ruleCode' => $code,
            'value' => 0,
            'modifiers' => [],
            'status' => $spec->getStatus(),
            'radius' => ['base' => $radius->getBase(), 'size' => $radius->getSize()],
        ];
    }

    /**
     * Эффекты состояний characteristic_modify.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Sheet.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Дельты.
     */
    private function stateDeltas(CharacterRuleSlice $slice, array $sheet): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return [];
        }

        $raw = [];
        foreach ($states as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = $row['stateRuleCode'] ?? null;
            $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
            if (!$spec instanceof StateSpec) {
                continue;
            }

            $units = $this->stateUnits($row);
            foreach ($spec->getEffects() as $effect) {
                if (!$effect instanceof CharacteristicModifyEffect) {
                    continue;
                }

                $delta = $effect->getAmount() * ($effect->isPerUnit() ? $units : 1);
                $source = is_string($code) ? $code : '';
                foreach ($this->penaltyTargets($slice, $effect->getCharacteristicCode()) as $target) {
                    $raw[$target][] = ['source' => $source, 'delta' => $delta];
                }
            }
        }

        return $raw;
    }

    /**
     * Единицы значения состояния.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Единицы.
     */
    private function stateUnits(array $row): int
    {
        $value = $row['value'] ?? 1;
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && is_int($value['base'] ?? null)) {
            return $value['base'];
        }

        return 1;
    }

    /**
     * Модификатор производной раскладывается на характеристики формулы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код из эффекта.
     *
     * @return array<int, string> Коды, на которые ложится дельта.
     */
    private function penaltyTargets(CharacterRuleSlice $slice, string $code): array
    {
        $spec = $slice->findLive($code)?->getSpec();
        $formula = $spec instanceof CharacteristicSpec ? $spec->getFormula() : null;
        $parsed = is_string($formula) ? $this->parsedFormula($formula) : null;

        return $parsed === null ? [$code] : $parsed['codes'];
    }

    /**
     * min(a, b) или max(a, b).
     *
     * @param string $formula Формула.
     *
     * @return array{max: bool, codes: array<int, string>}|null Разбор или null.
     */
    private function parsedFormula(string $formula): ?array
    {
        if (preg_match('/^(min|max)\(\s*([^,]+)\s*,\s*([^)]+)\)$/i', trim($formula), $match) !== 1) {
            return null;
        }

        return ['max' => strtolower($match[1]) === 'max', 'codes' => [trim($match[2]), trim($match[3])]];
    }

    /**
     * Параметры формулы донора: выбор, затем раса, затем default spec.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterDonorGrant $grant Грант.
     *
     * @return array<string, int> Код → значение.
     */
    private function parametersOf(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterDonorGrant $grant,
    ): array {
        $code = $grant->getDonorCode();
        foreach ($choices->getAbilities() as $ability) {
            if ($ability->getRuleCode() === $code && $ability->getParameters() !== []) {
                return $ability->getParameters() + $this->parameterDefaults($slice, $code);
            }
        }

        $fromRace = $this->raceAbilityCodes->getParameters($slice, $choices->getRaceCode(), $code);
        if ($fromRace !== []) {
            return $fromRace + $this->parameterDefaults($slice, $code);
        }

        return $this->parameterDefaults($slice, $code);
    }

    /**
     * Default скаляров spec способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Способность.
     *
     * @return array<string, int> Код → значение.
     */
    private function parameterDefaults(CharacterRuleSlice $slice, string $code): array
    {
        $spec = $slice->findLive($code)?->getSpec();
        if (
            !$spec instanceof AbilityPlainSpec
            && !$spec instanceof AbilityActionSpec
            && !$spec instanceof AbilitySpellSpec
            && !$spec instanceof AbilityProcessSpec
        ) {
            return [];
        }

        $values = [];
        foreach ($spec->getBase()->getParameters() as $parameter) {
            $scalar = $this->scalarDefault($parameter);
            if ($scalar !== null) {
                $values[$parameter->getCode()] = $scalar;
            }
        }

        return $values;
    }

    /**
     * Скаляр default. Размерный — null.
     *
     * @param AbilityParameter $parameter Параметр.
     *
     * @return int|null Значение.
     */
    private function scalarDefault(AbilityParameter $parameter): ?int
    {
        $default = $parameter->getDefault();
        if (is_int($default)) {
            return $default;
        }

        return $default->getSize() === 0 ? $default->getBase() : null;
    }

    /**
     * Скаляр гранта.
     *
     * @param CharacteristicModifyGrant $grant Грант.
     * @param FormulaContext $context Контекст.
     *
     * @return int Величина.
     */
    private function amountOf(CharacteristicModifyGrant $grant, FormulaContext $context): int
    {
        try {
            return $this->evaluations->evaluateScalar($grant->getAmount(), $context);
        } catch (RuleInvalidException) {
            return 0;
        }
    }

    /**
     * Max+ и max− на источник.
     *
     * @param array<string, array<int, array{source: string, delta: int}>> $raw Сырые.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Схлопнутые.
     */
    private function aggregate(array $raw): array
    {
        $out = [];
        foreach ($raw as $code => $rows) {
            $bySource = [];
            foreach ($rows as $row) {
                $bySource[$row['source']][] = $row['delta'];
            }

            foreach ($bySource as $source => $deltas) {
                $plus = 0;
                $minus = 0;
                foreach ($deltas as $delta) {
                    if ($delta > $plus) {
                        $plus = $delta;
                    }

                    if ($delta < $minus) {
                        $minus = $delta;
                    }
                }

                $out[$code][] = ['source' => (string) $source, 'delta' => $plus + $minus];
            }
        }

        return $out;
    }

    /**
     * base_from: база донора плюс выбранные источники.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array<string, array<int, array{source: string, delta: int}>> $deltas Дельты.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function baseFrom(CharacterRuleSlice $slice, array $bases, array $deltas): array
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            $from = $spec instanceof CharacteristicSpec ? $spec->getBaseFrom() : null;
            if ($from === null) {
                continue;
            }

            $donor = $bases[$from->getCharacteristicCode()] ?? null;
            if ($donor === null) {
                continue;
            }

            $allowed = array_fill_keys($from->getSourceCodes(), true);
            $delta = 0;
            foreach ($deltas[$from->getCharacteristicCode()] ?? [] as $row) {
                if (isset($allowed[$row['source']])) {
                    $delta += $row['delta'];
                }
            }

            $bases[$rule->getCode()] = $this->shifted($donor, $delta);
        }

        return $bases;
    }

    /**
     * База плюс сумма дельт.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array<string, array<int, array{source: string, delta: int}>> $deltas Дельты.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function applied(array $bases, array $deltas): array
    {
        $values = [];
        foreach ($bases as $code => $base) {
            $delta = 0;
            foreach ($deltas[$code] ?? [] as $row) {
                $delta += $row['delta'];
            }

            $values[$code] = $this->shifted($base, $delta);
        }

        return $values;
    }

    /**
     * min/max группы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, CharacteristicNumber> $values Значения.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function derived(CharacterRuleSlice $slice, array $values): array
    {
        $groups = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if (!$spec instanceof CharacteristicSpec) {
                continue;
            }

            $group = $spec->getGroup();
            if ($group !== null && $group !== '') {
                $groups[$group][] = $rule->getCode();
            }
        }

        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            $formula = $spec instanceof CharacteristicSpec ? $spec->getFormula() : null;
            $parsed = is_string($formula) ? $this->parsedFormula($formula) : null;
            if ($parsed !== null) {
                $picked = $this->pick($values, $parsed['codes'], $parsed['max']);
                if ($picked !== null) {
                    $values[$rule->getCode()] = $picked;
                }

                continue;
            }

            if ($formula !== 'min' && $formula !== 'max') {
                continue;
            }

            $group = $spec instanceof CharacteristicSpec ? $spec->getGroup() : null;
            $codes = $groups[$group ?? ''] ?? [];
            $picked = $this->pick($values, $codes, $formula === 'max');
            if ($picked !== null) {
                $values[$rule->getCode()] = $picked;
            }
        }

        return $values;
    }

    /**
     * Жёсткий потолок characteristic_limits надетого.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, CharacteristicNumber> $values Значения.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function capped(CharacterRuleSlice $slice, CharacterChoices $choices, array $values): array
    {
        $context = new FormulaContext($values, []);
        foreach ($this->equippedLive->equipped($slice, $choices) as $item) {
            $spec = $item['spec'];
            if (!$spec instanceof ItemSpec) {
                continue;
            }

            $limits = array_merge(
                $spec->getArmor()?->getCharacteristicLimits() ?? [],
                $spec->getShield()?->getCharacteristicLimits() ?? [],
            );
            foreach ($limits as $limit) {
                try {
                    $cap = $this->evaluations->evaluateDimensional($limit->getLimit(), $context);
                } catch (RuleInvalidException) {
                    continue;
                }

                $code = $limit->getCharacteristicCode();
                $current = $values[$code] ?? null;
                if ($current !== null && $current->compare($cap) > 0) {
                    $values[$code] = new CharacteristicNumber($cap->getBase(), $cap->getSize());
                }
            }
        }

        return $values;
    }

    /**
     * Строки sheet.characteristics.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array<string, CharacteristicNumber> $values Значения.
     * @param array<string, array<int, array{source: string, delta: int}>> $deltas Дельты.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function rows(array $bases, array $values, array $deltas): array
    {
        $rows = [];
        foreach ($values as $code => $value) {
            $base = $bases[$code] ?? $value;
            $delta = 0;
            $modifiers = [];
            foreach ($deltas[$code] ?? [] as $row) {
                $delta += $row['delta'];
                $modifiers[] = ['sourceRuleCode' => $row['source'], 'delta' => $row['delta']];
            }

            $rows[] = [
                'characteristicCode' => $code,
                'ruleCode' => $code,
                'base' => ['base' => $base->getBase(), 'size' => $base->getSize()],
                'value' => ['base' => $value->getBase(), 'size' => $value->getSize()],
                'delta' => $delta,
                'modifiers' => $modifiers,
            ];
        }

        return $rows;
    }

    /**
     * Сдвиг по шкале характеристики.
     *
     * @param CharacteristicNumber $number База.
     * @param int $delta Пункты.
     *
     * @return CharacteristicNumber Число.
     */
    private function shifted(CharacteristicNumber $number, int $delta): CharacteristicNumber
    {
        if ($delta === 0) {
            return $number;
        }

        try {
            $shifted = $number->modify($delta);
        } catch (RuleInvalidException) {
            return $number;
        }

        if ($shifted instanceof CharacteristicNumber) {
            return $shifted;
        }

        return new CharacteristicNumber($shifted->getBase(), $shifted->getSize());
    }

    /**
     * min или max списка.
     *
     * @param array<string, CharacteristicNumber> $values Значения.
     * @param array<int, string> $codes Коды группы.
     * @param bool $max true — максимум.
     *
     * @return CharacteristicNumber|null Число.
     */
    private function pick(array $values, array $codes, bool $max): ?CharacteristicNumber
    {
        $picked = null;
        foreach ($codes as $code) {
            $value = $values[$code] ?? null;
            if ($value === null) {
                continue;
            }

            if ($picked === null) {
                $picked = $value;
                continue;
            }

            $cmp = $value->compare($picked);
            if (($max && $cmp > 0) || (!$max && $cmp < 0)) {
                $picked = $value;
            }
        }

        return $picked;
    }
}
