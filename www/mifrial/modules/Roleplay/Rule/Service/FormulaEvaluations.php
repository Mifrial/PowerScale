<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Service;

use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\CharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\AbilityLevelScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizeGapScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizePositiveScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizeScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\FixedScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterFloorDivScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ToScalar;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Считает скалярный узел в int и размерный узел в DimensionalNumber.
 */
final class FormulaEvaluations implements IFormulaEvaluations
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
    public function evaluateScalar(ScalarFormula $node, FormulaContext $context): int
    {
        if ($node instanceof FixedScalar) {
            return $node->getValue();
        }

        if ($node instanceof ParameterScalar || $node instanceof ParameterFloorDivScalar) {
            return $this->evaluateParameter($node, $context);
        }

        return $this->evaluateLevelOrSize($node, $context);
    }

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
    public function evaluateDimensional(DimensionalFormula $node, FormulaContext $context): DimensionalNumber
    {
        return $this->walkDimensional($node, $context);
    }

    /**
     * Параметр или целая часть параметра.
     *
     * @param ParameterScalar|ParameterFloorDivScalar $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return int Число.
     *
     * @throws RuleInvalidException Если параметра нет или делитель равен нулю.
     */
    private function evaluateParameter(ParameterScalar|ParameterFloorDivScalar $node, FormulaContext $context): int
    {
        $value = $context->findParameter($node->getParameterCode());
        if ($value === null) {
            throw new RuleInvalidException('Нет параметра «' . $node->getParameterCode() . '»');
        }

        if ($node instanceof ParameterScalar) {
            return $value * $node->getPerUnit();
        }

        return $this->floorQuotient($value, $node->getDivisor());
    }

    /**
     * Уровень, размер или to_scalar.
     *
     * @param ScalarFormula $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return int Число.
     *
     * @throws RuleInvalidException Если узел неизвестен.
     */
    private function evaluateLevelOrSize(ScalarFormula $node, FormulaContext $context): int
    {
        if ($node instanceof AbilityLevelScalar) {
            return $this->abilityLevel($node, $context);
        }

        if ($node instanceof ToScalar) {
            return $this->mediumBase($this->walkDimensional($node->getValue(), $context));
        }

        return $this->evaluateSize($node, $context);
    }

    /**
     * Узел размера характеристики.
     *
     * @param ScalarFormula $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return int Число.
     *
     * @throws RuleInvalidException Если узел неизвестен.
     */
    private function evaluateSize(ScalarFormula $node, FormulaContext $context): int
    {
        if ($node instanceof CharacteristicSizeScalar) {
            return $context->findCharacteristic($node->getCharacteristicCode())?->getSize() ?? 0;
        }

        if ($node instanceof CharacteristicSizePositiveScalar) {
            return max(0, $context->findCharacteristic($node->getCharacteristicCode())?->getSize() ?? 0);
        }

        if ($node instanceof CharacteristicSizeGapScalar) {
            return $this->sizeGap($node, $context);
        }

        throw new RuleInvalidException('Неизвестный узел формулы');
    }

    /**
     * Уровень способности. Нет множителя — 1, нет смещения — 0.
     *
     * @param AbilityLevelScalar $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return int Число.
     */
    private function abilityLevel(AbilityLevelScalar $node, FormulaContext $context): int
    {
        $level = $context->findAbilityLevel($node->getAbilityCode());

        return $level * ($node->getMultiplier() ?? 1) + ($node->getOffset() ?? 0);
    }

    /**
     * Число полных размеров, на которое from выше to. Нет одной из пар — 0.
     *
     * @param CharacteristicSizeGapScalar $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return int Число.
     */
    private function sizeGap(CharacteristicSizeGapScalar $node, FormulaContext $context): int
    {
        $from = $context->findCharacteristic($node->getCharacteristicCodeFrom());
        $to = $context->findCharacteristic($node->getCharacteristicCodeTo());
        if ($from === null || $to === null) {
            return 0;
        }

        $step = CharacteristicNumber::BASE_MAX - CharacteristicNumber::BASE_MIN + 1;
        $delta = $from->getBase() + $step * $from->getSize() - ($to->getBase() + $step * $to->getSize());

        return intdiv($delta, $step);
    }

    /**
     * База пары, перенесённой на средний размер.
     *
     * @param DimensionalNumber $number Пара.
     *
     * @return int База.
     */
    private function mediumBase(DimensionalNumber $number): int
    {
        $step = CharacteristicNumber::BASE_MAX - CharacteristicNumber::BASE_MIN + 1;
        $shifted = $number->modify(-$number->getSize() * $step);

        return $shifted->getBase();
    }

    /**
     * Размерный узел как пара.
     *
     * @param DimensionalFormula $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws RuleInvalidException Если узел неизвестен или характеристики нет.
     */
    private function walkDimensional(DimensionalFormula $node, FormulaContext $context): DimensionalNumber
    {
        if ($node instanceof FixedNode) {
            return new DimensionalNumber($node->getValue(), 0);
        }

        if ($node instanceof DimensionalNode) {
            return $node->getNumber();
        }

        if ($node instanceof CharacteristicNode || $node instanceof ActionCharacteristicNode) {
            return $this->walkCharacteristic($node, $context);
        }

        throw new RuleInvalidException('Неизвестный узел формулы');
    }

    /**
     * Характеристика или характеристика действия.
     *
     * @param CharacteristicNode|ActionCharacteristicNode $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws RuleInvalidException Если характеристики нет.
     */
    private function walkCharacteristic(
        CharacteristicNode|ActionCharacteristicNode $node,
        FormulaContext $context,
    ): DimensionalNumber {
        if ($node instanceof CharacteristicNode) {
            return $this->shiftCharacteristic(
                $context->findCharacteristic($node->getCharacteristicCode()),
                $node->getModifier(),
                $node->getCharacteristicCode(),
            );
        }

        return $this->walkAction($node, $context);
    }

    /**
     * Характеристика действия: база профиля или характеристика персонажа.
     *
     * @param ActionCharacteristicNode $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws RuleInvalidException Если характеристики нет.
     */
    private function walkAction(ActionCharacteristicNode $node, FormulaContext $context): DimensionalNumber
    {
        $base = $context->findActionCharacteristic($node->getAction(), $node->getCharacteristic());
        $base ??= $context->findCharacteristic($node->getCharacteristic());
        $delta = 0;
        foreach ($node->getModifiers() as $modifier) {
            $delta += $modifier->getDelta();
        }

        $modified = $this->shiftCharacteristic($base, $delta, $node->getCharacteristic());
        $multiplier = $node->getMultiplier();
        if ($multiplier === null || $multiplier === 0) {
            return $modified;
        }

        return new DimensionalNumber($modified->getBase() * $multiplier, $modified->getSize());
    }

    /**
     * Сдвигает базу по шкале характеристик.
     *
     * @param DimensionalNumber|null $base Пара.
     * @param int $delta Пункты.
     * @param string $code Код для отказа.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws RuleInvalidException Если пары нет.
     */
    private function shiftCharacteristic(?DimensionalNumber $base, int $delta, string $code): DimensionalNumber
    {
        if ($base === null) {
            throw new RuleInvalidException('Нет характеристики «' . $code . '»');
        }

        return $base->modify($delta);
    }

    /**
     * Целая часть частного вниз, как Math.floor.
     *
     * @param int $dividend Делимое.
     * @param int $divisor Делитель.
     *
     * @return int Частное.
     *
     * @throws RuleInvalidException Если делитель равен нулю.
     */
    private function floorQuotient(int $dividend, int $divisor): int
    {
        if ($divisor === 0) {
            throw new RuleInvalidException('Делитель parameter_floor_div равен 0');
        }

        $quotient = intdiv($dividend, $divisor);
        if ($dividend % $divisor !== 0 && ($dividend < 0) !== ($divisor < 0)) {
            return $quotient - 1;
        }

        return $quotient;
    }
}
