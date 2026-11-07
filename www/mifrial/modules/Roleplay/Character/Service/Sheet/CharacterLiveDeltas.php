<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterCharacteristicFormula;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterGrantParameters;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Дельты характеристик: способности, предметы, состояния и чувства.
 */
final class CharacterLiveDeltas
{
    /**
     * Создаёт сбор дельт.
     *
     * @param IFormulaEvaluations $evaluations Формулы.
     * @param CharacterAbilityGrantReader $grantReader Гранты способностей.
     * @param CharacterGrantParameters $parameters Параметры формул.
     * @param CharacterEquippedLive $equippedLive Надетое.
     * @param CharacterStateDeltas $stateDeltas Дельты состояний.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $evaluations,
        private readonly CharacterAbilityGrantReader $grantReader,
        private readonly CharacterGrantParameters $parameters,
        private readonly CharacterEquippedLive $equippedLive,
        private readonly CharacterStateDeltas $stateDeltas,
    ) {
    }

    /**
     * Собирает и схлопывает дельты характеристик.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array{source: string, delta: int}|null $senseDelta Вклад чувств или null.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Дельты.
     */
    public function compute(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $sheet,
        array $abilityLevels,
        array $bases,
        ?array $senseDelta,
    ): array {
        $raw = $this->grantDeltas($slice, $choices, $abilityLevels, $bases);
        $raw = $this->equipmentDeltas($raw, $slice, $choices, $abilityLevels, $bases);
        foreach ($this->stateDeltas->compute($slice, $sheet) as $code => $rows) {
            foreach ($rows as $row) {
                $raw[$code][] = $row;
            }
        }

        if ($senseDelta !== null) {
            $raw['attention'][] = $senseDelta;
        }

        return $this->aggregate($raw);
    }

    /**
     * Дельты грантов выбранных и расовых способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Сырые дельты.
     */
    private function grantDeltas(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
    ): array {
        $raw = [];
        foreach ($this->grantReader->grants($slice, $choices, $abilityLevels) as $grant) {
            $body = $grant->getGrant();
            if (!$body instanceof CharacteristicModifyGrant || $body->getCheckCodes() !== []) {
                continue;
            }

            $context = new FormulaContext(
                $bases,
                $abilityLevels,
                $this->parameters->forGrant($slice, $choices, $grant),
            );
            $raw = $this->applyDelta(
                $raw,
                $slice,
                $body->getCharacteristicCode(),
                $body->getSourceCode(),
                $this->amountOf($body, $context),
            );
        }

        return $raw;
    }

    /**
     * Дельты characteristic_modify надетых предметов.
     *
     * @param array<string, array<int, array{source: string, delta: int}>> $raw Сырые дельты.
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, CharacteristicNumber> $bases Базы.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Сырые дельты.
     */
    private function equipmentDeltas(
        array $raw,
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        array $bases,
    ): array {
        $context = new FormulaContext($bases, $abilityLevels);
        foreach ($this->equippedLive->equipped($slice, $choices) as $item) {
            foreach ($item['spec']->getGrants() as $grant) {
                if (!$grant instanceof CharacteristicModifyGrant || $grant->getCheckCodes() !== []) {
                    continue;
                }

                $raw = $this->applyDelta(
                    $raw,
                    $slice,
                    $grant->getCharacteristicCode(),
                    $item['ruleCode'],
                    $this->amountOf($grant, $context),
                );
            }
        }

        return $raw;
    }

    /**
     * Кладёт дельту на код характеристики и его формулу.
     *
     * @param array<string, array<int, array{source: string, delta: int}>> $raw Сырые дельты.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код характеристики.
     * @param string $source Источник.
     * @param int $delta Величина.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Сырые дельты.
     */
    private function applyDelta(
        array $raw,
        CharacterRuleSlice $slice,
        string $code,
        string $source,
        int $delta,
    ): array {
        foreach (CharacterCharacteristicFormula::targetCodes($slice, $code) as $target) {
            $raw[$target][] = ['source' => $source, 'delta' => $delta];
        }

        return $raw;
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
            foreach ($this->bySource($rows) as $source => $deltas) {
                $out[$code][] = [
                    'source' => (string) $source,
                    'delta' => $this->maxPlusMinus($deltas),
                ];
            }
        }

        return $out;
    }

    /**
     * Группирует дельты по источнику.
     *
     * @param array<int, array{source: string, delta: int}> $rows Дельты.
     *
     * @return array<string, array<int, int>> Источник → величины.
     */
    private function bySource(array $rows): array
    {
        $bySource = [];
        foreach ($rows as $row) {
            $bySource[$row['source']][] = $row['delta'];
        }

        return $bySource;
    }

    /**
     * Сумма максимума положительных и минимума отрицательных.
     *
     * @param array<int, int> $deltas Величины.
     *
     * @return int Итог.
     */
    private function maxPlusMinus(array $deltas): int
    {
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

        return $plus + $minus;
    }
}
