<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Interface\Service;

use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Обход уже разобранных узлов формулы.
 */
interface IFormulaEvaluations
{
    /**
     * Считает скалярный узел.
     *
     * @param ScalarFormula $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return int Число.
     *
     * @throws RuleInvalidException Если узел неизвестен или вход узла недопустим.
     */
    public function evaluateScalar(ScalarFormula $node, FormulaContext $context): int;

    /**
     * Считает размерный узел.
     *
     * @param DimensionalFormula $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws RuleInvalidException Если узел неизвестен или характеристики нет.
     */
    public function evaluateDimensional(DimensionalFormula $node, FormulaContext $context): DimensionalNumber;
}
