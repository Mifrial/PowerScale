<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Resource;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceLimitChangeGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceAdjustment;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Собирает строки ресурсов по live-правилам и грантам.
 */
final class CharacterResourceLimits
{
    /**
     * Создаёт калькулятор effective limit.
     *
     * @param IFormulaEvaluations $evaluations Formula evaluator.
     * @param CharacterResourceGrantReader $grantReader Ability grant reader.
     * @param CharacterResourceArithmetic $arithmetic Native arithmetic.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $evaluations,
        private readonly CharacterResourceGrantReader $grantReader,
        private readonly CharacterResourceArithmetic $arithmetic,
    ) {
    }

    /**
     * Собирает initialized и clamped rows.
     *
     * @param CharacterRuleSlice $slice Live rules.
     * @param CharacterValidation $validation Validated character snapshot.
     * @param CharacterChoices $choices Selected abilities.
     * @param array<int, ResourceRow> $previousRows Existing typed rows.
     *
     * @return array<int, ResourceRow> Rows to persist.
     *
     * @throws CharacterInvalidException If a grant or variant is invalid.
     */
    public function buildRows(
        CharacterRuleSlice $slice,
        CharacterValidation $validation,
        CharacterChoices $choices,
        array $previousRows = [],
    ): array {
        $previous = $this->indexPrevious($previousRows);
        $context = $this->context($validation);
        $grants = $this->grantReader->getByResource($slice, $choices);
        $rows = [];

        foreach ($slice->getLiveRules() as $rule) {
            $row = $this->buildRow($rule->getCode(), $rule->getSpec(), $grants, $context, $previous);
            if ($row instanceof ResourceRow) {
                $rows[] = $row;
            }
        }

        foreach ($previous as $code => $_row) {
            if ($slice->findLive($code) === null) {
                throw new CharacterInvalidException('Resource rule was removed');
            }
        }

        return $rows;
    }

    /**
     * Собирает одну строку ресурса.
     *
     * @param string $code Resource code.
     * @param mixed $spec Live spec.
     * @param array<string, array<int, ResourceGrant|ResourceLimitChangeGrant>> $grants Grants.
     * @param FormulaContext $context Formula context.
     * @param array<string, ResourceRow> $previous Previous rows.
     *
     * @return ResourceRow|null Row or no candidate.
     *
     * @throws CharacterInvalidException При неверном resource spec.
     */
    private function buildRow(
        string $code,
        mixed $spec,
        array $grants,
        FormulaContext $context,
        array $previous,
    ): ?ResourceRow {
        if (!$spec instanceof ResourceSpec) {
            return null;
        }

        $resourceGrants = $grants[$code] ?? [];
        $previousRow = $previous[$code] ?? null;
        if ($previousRow === null && !$spec->isAutoAdd() && $resourceGrants === []) {
            return null;
        }

        $limit = $this->limit($spec, $resourceGrants, $context);
        $current = $previousRow?->getCurrent() ?? $this->arithmetic->value($limit);

        return new ResourceRow($code, $this->arithmetic->clamp($current, $limit));
    }

    /**
     * Собирает FormulaContext из проверенных значений листа.
     *
     * @param CharacterValidation $validation Validation snapshot.
     *
     * @return FormulaContext Formula context.
     */
    private function context(CharacterValidation $validation): FormulaContext
    {
        $characteristics = [];
        foreach ($validation->getPurchasedCharacteristics() as $purchase) {
            $value = $purchase->getValue();
            $characteristics[$purchase->getCharacteristicCode()] = new CharacteristicNumber(
                $value->getBase(),
                $value->getSize(),
            );
        }

        return new FormulaContext($characteristics, $validation->getAbilityLevels());
    }

    /**
     * Считает один effective native limit.
     *
     * @param ResourceSpec $spec Resource rule.
     * @param array<int, ResourceGrant|ResourceLimitChangeGrant> $grants Applicable grants.
     * @param FormulaContext $context Formula context.
     *
     * @return int|DimensionalNumber Effective limit.
     *
     * @throws CharacterInvalidException If a variant or formula is invalid.
     */
    private function limit(
        ResourceSpec $spec,
        array $grants,
        FormulaContext $context,
    ): int|DimensionalNumber {
        $delta = 0;
        $limit = $spec->getLimit();
        if ($limit !== null) {
            foreach ($limit->getAdjustments() as $adjustment) {
                $delta += $this->evaluate($adjustment, $context);
            }
        }

        foreach ($grants as $grant) {
            if ($grant instanceof ResourceLimitChangeGrant) {
                $delta += $this->evaluateAmount($grant, $context);
            }
        }

        return $this->arithmetic->applyDelta(
            $this->arithmetic->getBase($spec, $grants),
            $delta,
        );
    }

    /**
     * Вычисляет формулу поправки ресурса.
     *
     * @param ResourceAdjustment $adjustment Adjustment.
     * @param FormulaContext $context Formula context.
     *
     * @return int Scalar delta.
     *
     * @throws CharacterInvalidException If formula evaluation fails.
     */
    private function evaluate(ResourceAdjustment $adjustment, FormulaContext $context): int
    {
        try {
            return $this->evaluations->evaluateScalar($adjustment->getValue(), $context);
        } catch (RuleInvalidException $exception) {
            throw new CharacterInvalidException('Resource limit formula is invalid', $exception);
        }
    }

    /**
     * Вычисляет grant изменения лимита.
     *
     * @param ResourceLimitChangeGrant $grant Limit modifier.
     * @param FormulaContext $context Formula context.
     *
     * @return int Scalar delta.
     *
     * @throws CharacterInvalidException If formula evaluation fails.
     */
    private function evaluateAmount(ResourceLimitChangeGrant $grant, FormulaContext $context): int
    {
        try {
            return $this->evaluations->evaluateScalar($grant->getAmount(), $context);
        } catch (RuleInvalidException $exception) {
            throw new CharacterInvalidException('Resource limit grant is invalid', $exception);
        }
    }

    /**
     * Индексирует предыдущие строки.
     *
     * @param array<int, ResourceRow> $rows Existing rows.
     *
     * @return array<string, ResourceRow> Code-indexed rows.
     *
     * @throws CharacterInvalidException If a row is duplicated.
     */
    private function indexPrevious(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!$row instanceof ResourceRow) {
                throw new CharacterInvalidException('Resource row is invalid');
            }

            $code = $row->getRuleCode();
            if (isset($indexed[$code])) {
                throw new CharacterInvalidException('Resource row is duplicated');
            }

            $indexed[$code] = $row;
        }

        return $indexed;
    }
}
