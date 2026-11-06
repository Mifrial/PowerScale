<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeAttack;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackReachEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DistanceValue;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Spec\Formulas;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Дистанция удара: границы профиля, дальнобойность и штраф силы броска.
 */
final class GameStrikeDistance
{
    /**
     * Принимает обход формулы и шаг расы.
     *
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     * @param GameStrikeMovement $movement Шаг расы и способностей.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $formulaEvaluations,
        private readonly GameStrikeMovement $movement,
    ) {
    }

    /**
     * Проверяет условия цели по дистанции.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     * @param array<string, mixed> $conditions Условия цели.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return void
     *
     * @throws GameInvalidException Если условия неверны.
     * @throws RuleInvalidException Если формула дистанции битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    public function assertConditions(GameStrikeAttack $attack, array $conditions, string $actionRuleCode): void
    {
        $this->metrics($attack, $conditions, $actionRuleCode);
    }

    /**
     * Метрики дистанции подготовленного удара.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     * @param array<string, mixed> $conditions Условия цели.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return array{distance: DimensionalNumber, min: DimensionalNumber, max: DimensionalNumber|null,
     *               falloff: DimensionalNumber|null, rangeSize: int, drop: int} Метрики.
     *
     * @throws GameInvalidException Если дистанция вне диапазона или не долетает.
     * @throws RuleInvalidException Если формула дистанции битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    public function metrics(GameStrikeAttack $attack, array $conditions, string $actionRuleCode): array
    {
        return $this->buildMetrics($attack, $conditions, $actionRuleCode);
    }

    /**
     * Считает все метрики одного удара.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     * @param array<string, mixed> $conditions Условия цели.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return array{distance: DimensionalNumber, min: DimensionalNumber, max: DimensionalNumber|null,
     *               falloff: DimensionalNumber|null, rangeSize: int, drop: int} Метрики.
     *
     * @throws GameInvalidException Если дистанция вне диапазона или не долетает.
     * @throws RuleInvalidException Если формула дистанции битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    private function buildMetrics(GameStrikeAttack $attack, array $conditions, string $actionRuleCode): array
    {
        $documents = $attack->documents;
        $step = $this->movement->step($attack->slice, $documents['sheet'], $documents['choices']);
        [$distance, $min, $max] = $this->bounds(
            $attack->slice,
            $attack->profile,
            $conditions,
            $attack->context,
            $step,
            $actionRuleCode,
        );
        [$falloff, $rangeSize] = $this->falloff($attack->profile, $attack->context, $step, $distance);
        $drop = $this->throwDrop($attack, $falloff, $distance, $min);

        return [
            'distance' => $distance,
            'min' => $min,
            'max' => $max,
            'falloff' => $falloff,
            'rangeSize' => $rangeSize,
            'drop' => $drop,
        ];
    }

    /**
     * Границы профиля и проверка попадания в них.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param WeaponProfile $profile Профиль.
     * @param array<string, mixed> $conditions Условия цели.
     * @param FormulaContext $context Контекст профиля.
     * @param DimensionalNumber $step Шаг.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return array{DimensionalNumber, DimensionalNumber, DimensionalNumber|null} Дистанция, минимум, максимум.
     *
     * @throws GameInvalidException Если дистанция вне диапазона.
     * @throws RuleInvalidException Если формула дистанции битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    private function bounds(
        CharacterRuleSlice $slice,
        WeaponProfile $profile,
        array $conditions,
        FormulaContext $context,
        DimensionalNumber $step,
        string $actionRuleCode,
    ): array {
        $distance = $this->resolveDistanceValue($conditions['distance'], $context, $step);
        $min = $this->resolveDistanceValue($profile->getDistance()->getMin(), $context, $step);
        $max = $this->maxDistance($slice, $profile, $context, $step, $actionRuleCode);
        $this->assertRange($distance, $min, $max);

        return [$distance, $min, $max];
    }

    /**
     * Максимальная дистанция профиля с бонусом «Выпада».
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param WeaponProfile $profile Профиль.
     * @param FormulaContext $context Контекст профиля.
     * @param DimensionalNumber $step Шаг.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return DimensionalNumber|null Максимум или null.
     *
     * @throws RuleInvalidException Если формула дистанции битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    private function maxDistance(
        CharacterRuleSlice $slice,
        WeaponProfile $profile,
        FormulaContext $context,
        DimensionalNumber $step,
        string $actionRuleCode,
    ): ?DimensionalNumber {
        $maxValue = $profile->getDistance()->getMax();
        if ($maxValue === null) {
            return null;
        }

        $max = $this->resolveDistanceValue($maxValue, $context, $step);

        return $max->add($this->reachBonus($slice, $actionRuleCode, $profile->getType(), $step));
    }

    /**
     * Дальнобойность и число диапазонов.
     *
     * @param WeaponProfile $profile Профиль.
     * @param FormulaContext $context Контекст профиля.
     * @param DimensionalNumber $step Шаг.
     * @param DimensionalNumber $distance Дистанция.
     *
     * @return array{DimensionalNumber|null, int} Дальнобойность и диапазоны.
     *
     * @throws RuleInvalidException Если формула дистанции битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    private function falloff(
        WeaponProfile $profile,
        FormulaContext $context,
        DimensionalNumber $step,
        DimensionalNumber $distance,
    ): array {
        $falloff = $profile->getFalloff();
        if ($falloff === null) {
            return [null, 1];
        }

        $value = $this->resolveDistanceValue($falloff, $context, $step);

        return [$value, $this->rangeSize($distance, $value)];
    }

    /**
     * Сколько диапазонов дальнобойности прошла дистанция.
     *
     * @param DimensionalNumber $distance Дистанция.
     * @param DimensionalNumber $falloff Дальнобойность.
     *
     * @return int Число диапазонов.
     *
     * @throws GameInvalidException Если диапазонов больше 32.
     */
    private function rangeSize(DimensionalNumber $distance, DimensionalNumber $falloff): int
    {
        $rangeSize = 1;
        $threshold = $falloff;
        while ($distance->compare($threshold) > 0) {
            ++$rangeSize;
            $threshold = $threshold->withSize($threshold->getSize() + 1);
            if ($rangeSize > 32) {
                throw new GameInvalidException('Game strike distance is out of range');
            }
        }

        return $rangeSize;
    }

    /**
     * Штраф силы броска от дальности.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     * @param DimensionalNumber|null $falloff Дальнобойность.
     * @param DimensionalNumber $distance Дистанция.
     * @param DimensionalNumber $min Минимум профиля.
     *
     * @return int Штраф в размерах.
     *
     * @throws GameInvalidException Если силы броска не хватает.
     * @throws RuleInvalidException Если деление отвергнуто.
     */
    private function throwDrop(
        GameStrikeAttack $attack,
        ?DimensionalNumber $falloff,
        DimensionalNumber $distance,
        DimensionalNumber $min,
    ): int {
        if ($attack->profile->getType() !== 'throw' || $falloff === null) {
            return 0;
        }

        $drop = $distance->subtract($min)->divide($falloff)->getQuotient();
        $this->assertThrowReaches($attack->item, $attack->context, $drop);

        return $drop;
    }

    /**
     * Проверяет, что силы броска хватает на дистанцию.
     *
     * @param ItemSpec $item Предмет.
     * @param FormulaContext $context Контекст профиля.
     * @param int $drop Штраф в размерах.
     *
     * @return void
     *
     * @throws GameInvalidException Если силы нет или она слишком мала.
     * @throws RuleInvalidException Если шкала характеристики битая.
     */
    private function assertThrowReaches(ItemSpec $item, FormulaContext $context, int $drop): void
    {
        $strength = $context->findActionCharacteristic('throw', 'strength')
            ?? $context->findCharacteristic('strength');
        if (!$strength instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike throw strength is invalid');
        }

        $strength = (new CharacteristicNumber($strength->getBase(), $strength->getSize()))
            ->modify(-CharacteristicNumber::BASE_STEP * $drop);
        $minStrength = $item->getWeapon()?->getMinStrength();
        if ($minStrength === null) {
            return;
        }

        $diff = $strength->getBase() + CharacteristicNumber::BASE_STEP * $strength->getSize()
            - ($minStrength->getBase() + CharacteristicNumber::BASE_STEP * $minStrength->getSize());
        if ($diff <= -2 * CharacteristicNumber::BASE_STEP) {
            throw new GameInvalidException('Game throw does not reach');
        }
    }

    /**
     * Бонус «Выпада» к максимальной дистанции.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Вид профиля.
     * @param DimensionalNumber $step Шаг.
     *
     * @return DimensionalNumber Бонус.
     */
    private function reachBonus(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        DimensionalNumber $step,
    ): DimensionalNumber {
        if ($profileType !== 'strike' || $actionRuleCode === '') {
            return new DimensionalNumber(0, 0);
        }

        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return new DimensionalNumber(0, 0);
        }

        $bonus = new DimensionalNumber(0, 0);
        foreach ($base->getActionEffects() as $effect) {
            $bonus = $bonus->add($this->reachEffect($effect, $profileType, $step));
        }

        return $bonus;
    }

    /**
     * Вклад одного эффекта досягаемости.
     *
     * @param mixed $effect Эффект карточки.
     * @param string $profileType Вид профиля.
     * @param DimensionalNumber $step Шаг.
     *
     * @return DimensionalNumber Слагаемое.
     */
    private function reachEffect(mixed $effect, string $profileType, DimensionalNumber $step): DimensionalNumber
    {
        $bonus = new DimensionalNumber(0, 0);
        if (
            $effect instanceof CurrentActionAttackReachEffect
            && $effect->getScope()->appliesTo($profileType, 1)
        ) {
            $fraction = $effect->getStepFraction();
            if ($fraction === 0.5) {
                $bonus = $step->withSize($step->getSize() - 1);
            } elseif ($fraction === 1) {
                $bonus = $step;
            }
        }

        return $bonus;
    }

    /**
     * Переводит DistanceValue в ипари.
     *
     * @param array<string, mixed>|DistanceValue $value DistanceValue.
     * @param FormulaContext $context Контекст профиля.
     * @param DimensionalNumber $step Шаг.
     *
     * @return DimensionalNumber Ипари.
     *
     * @throws RuleInvalidException Если формула битая.
     * @throws RuleSpecShapeException Если форма формулы чужая.
     */
    private function resolveDistanceValue(
        array|DistanceValue $value,
        FormulaContext $context,
        DimensionalNumber $step,
    ): DimensionalNumber {
        if ($value instanceof DistanceValue) {
            $type = $value->getType();
            $node = $value->getValue();
        } else {
            $type = $value['type'] ?? null;
            $node = Formulas::dimensionalNode($value['value']);
        }

        $distance = $this->formulaEvaluations->evaluateDimensional($node, $context);
        if ($type === 'ipari') {
            return $distance;
        }

        if ($type !== 'step') {
            throw new RuleInvalidException('Тип дистанции неизвестен');
        }

        return $distance->multiply($step);
    }

    /**
     * Дистанция внутри границ профиля.
     *
     * @param DimensionalNumber $distance Дистанция.
     * @param DimensionalNumber $min Минимум.
     * @param DimensionalNumber|null $max Максимум или null.
     *
     * @return void
     *
     * @throws GameInvalidException Если дистанция вне диапазона.
     */
    private function assertRange(
        DimensionalNumber $distance,
        DimensionalNumber $min,
        ?DimensionalNumber $max,
    ): void {
        if ($distance->compare($min) < 0 || ($max !== null && $distance->compare($max) > 0)) {
            throw new GameInvalidException('Game strike distance is out of range');
        }
    }
}
