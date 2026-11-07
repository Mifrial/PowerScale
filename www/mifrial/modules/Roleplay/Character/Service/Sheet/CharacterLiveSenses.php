<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterGrantParameters;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SenseModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\SenseSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Чувства листа: auto_add, гранты, статусы и лучший бонус.
 */
final class CharacterLiveSenses
{
    private const RANKS = ['precise' => 0, 'imprecise' => 1, 'vague' => 2, 'absent' => 3];

    private const NAMES = ['precise', 'imprecise', 'vague', 'absent'];

    /**
     * Создаёт расчёт чувств.
     *
     * @param IFormulaEvaluations $evaluations Формулы.
     * @param CharacterAbilityGrantReader $grantReader Гранты способностей.
     * @param CharacterGrantParameters $parameters Параметры формул.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $evaluations,
        private readonly CharacterAbilityGrantReader $grantReader,
        private readonly CharacterGrantParameters $parameters,
    ) {
    }

    /**
     * Активные чувства: auto_add и выданные грантом, без статуса «отсутствует».
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function active(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
    ): array {
        $active = [];
        foreach ($this->rows($slice, $choices, $abilityLevels, $bases) as $row) {
            if ($row['status'] === 'absent') {
                continue;
            }

            $active[] = $row;
        }

        return $active;
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
    public function rows(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
    ): array {
        $rows = $this->autoRows($slice);
        foreach ($this->grantReader->grants($slice, $choices, $abilityLevels) as $grant) {
            $rows = $this->applyGrant($rows, $slice, $choices, $abilityLevels, $bases, $grant);
        }

        return array_values($rows);
    }

    /**
     * Лучшее не-absent чувство. Бонус 0 не даёт модификатора.
     *
     * @param array<int, array<string, mixed>> $rows Чувства.
     *
     * @return array{source: string, delta: int}|null Вклад в Внимательность.
     */
    public function bestDelta(array $rows): ?array
    {
        $bestCode = null;
        $best = null;
        foreach ($rows as $row) {
            if (!$this->countableSense($row)) {
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
     * Чувства с auto_add.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, array<string, mixed>> Код → строка.
     */
    private function autoRows(CharacterRuleSlice $slice): array
    {
        $rows = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof SenseSpec && $spec->isAutoAdd()) {
                $rows[$rule->getCode()] = $this->emptySense($rule->getCode(), $spec);
            }
        }

        return $rows;
    }

    /**
     * Накладывает один sense-грант на строки.
     *
     * @param array<string, array<string, mixed>> $rows Код → строка.
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param \Mifrial\Roleplay\Character\Dto\CharacterDonorGrant $grant Грант.
     *
     * @return array<string, array<string, mixed>> Код → строка.
     */
    private function applyGrant(
        array $rows,
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
        CharacterDonorGrant $grant,
    ): array {
        $body = $grant->getGrant();
        if (!$body instanceof SenseModifyGrant) {
            return $rows;
        }

        $code = $body->getSenseCode();
        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof SenseSpec) {
            return $rows;
        }

        if (!isset($rows[$code])) {
            $rows[$code] = $this->emptySense($code, $spec);
        }

        $delta = $this->senseAmount($slice, $choices, $abilityLevels, $bases, $grant, $body);

        return $delta === null ? $rows : $this->applySenseDelta($rows, $code, $body, $delta);
    }

    /**
     * Скаляр гранта чувства. Ошибка формулы — null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param CharacterDonorGrant $grant Грант.
     * @param SenseModifyGrant $body Тело гранта.
     *
     * @return int|null Величина или null.
     */
    private function senseAmount(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
        CharacterDonorGrant $grant,
        SenseModifyGrant $body,
    ): ?int {
        $context = new FormulaContext(
            $bases,
            $abilityLevels,
            $this->parameters->forGrant($slice, $choices, $grant),
        );
        try {
            return $this->evaluations->evaluateScalar($body->getAmount(), $context);
        } catch (RuleInvalidException) {
            return null;
        }
    }

    /**
     * Записывает величину, источник, статус и порог гранта.
     *
     * @param array<string, array<string, mixed>> $rows Код → строка.
     * @param string $code Код чувства.
     * @param SenseModifyGrant $body Тело гранта.
     * @param int $delta Величина.
     *
     * @return array<string, array<string, mixed>> Код → строка.
     */
    private function applySenseDelta(array $rows, string $code, SenseModifyGrant $body, int $delta): array
    {
        $rows[$code]['value'] += $delta;
        $rows[$code]['modifiers'][] = [
            'sourceRuleCode' => $body->getSourceCode(),
            'delta' => $delta,
        ];
        $rows[$code]['status'] = $this->raisedStatus($rows[$code]['status'], $body->getStatus());

        if ($body->getTreatAsGoodDownTo() !== null) {
            $rows[$code]['treatAsGoodDownTo'] = $body->getTreatAsGoodDownTo();
        }

        return $rows;
    }

    /**
     * Более сильный статус из двух.
     *
     * @param mixed $current Текущий статус.
     * @param string|null $status Новый статус или null.
     *
     * @return mixed Статус.
     */
    private function raisedStatus(mixed $current, ?string $status): mixed
    {
        $currentRank = self::RANKS[$current] ?? 0;
        if ($status === null || (self::RANKS[$status] ?? -1) <= $currentRank) {
            return $current;
        }

        return self::NAMES[self::RANKS[$status]];
    }

    /**
     * Строка участвует в выборе лучшего чувства.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return bool true, если статус не absent и значение с кодом целые.
     */
    private function countableSense(array $row): bool
    {
        return $row['status'] !== 'absent'
            && is_int($row['value'])
            && is_string($row['ruleCode']);
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
}
