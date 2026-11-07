<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CounterThreshold;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StepCost;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ChainTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\CustomTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\TransitionEdge;

/**
 * Чистые правила процесса: шаги, переходы и цена шага.
 */
final class GameProcessRules
{
    /**
     * Живая спека процесса.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Карточка процесса.
     *
     * @return ProcessSpec Тело.
     *
     * @throws GameInvalidException Если карточка не живая или не процесс.
     */
    public function spec(CharacterRuleSlice $slice, string $code): ProcessSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilityProcessSpec) {
            throw new GameInvalidException('Game process card is not live');
        }

        return $spec->getProcess();
    }

    /**
     * Шаг по коду.
     *
     * @param ProcessSpec $spec Тело.
     * @param string $code Код шага.
     *
     * @return ProcessStep Шаг.
     *
     * @throws GameInvalidException Если шага нет.
     */
    public function step(ProcessSpec $spec, string $code): ProcessStep
    {
        $steps = $spec->getSteps();

        return $steps[$this->currentIndex($steps, $code)];
    }

    /**
     * Доступные шаги из текущего.
     *
     * @param ProcessSpec $spec Тело.
     * @param string|null $current Текущий шаг или null для старта.
     * @param string|null $status Статус текущего шага.
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return array<int, string> Коды.
     *
     * @throws GameInvalidException Если текущий шаг чужой.
     */
    public function availableSteps(
        ProcessSpec $spec,
        ?string $current,
        ?string $status,
        array $counters = [],
    ): array {
        if ($current === null || $current === '') {
            return $this->filterByCounters($spec, [$spec->getStartStepCode()], $counters);
        }

        if ($status === 'pending') {
            return [$current];
        }

        $steps = $spec->getSteps();
        $index = $this->currentIndex($steps, $current);
        $codes = $this->transitionCodes($spec, $steps, $index, $current);

        return $this->filterByCounters($spec, array_values(array_unique($codes)), $counters);
    }

    /**
     * Стартовые счётчики сессии из спеки.
     *
     * @param ProcessSpec $spec Тело.
     *
     * @return array<string, int> Карта код → значение.
     */
    public function initialCounters(ProcessSpec $spec): array
    {
        $counters = [];
        foreach ($spec->getCounters() as $counter) {
            $counters[$counter->getCode()] = $counter->getInitial();
        }

        return $counters;
    }

    /**
     * Счётчики после шага: успех — counter_on_success, провал с
     * restart_from_first — counter_on_failure. Порядок списка сохраняется.
     *
     * @param ProcessStep $step Шаг.
     * @param array<string, mixed> $counters Текущие счётчики.
     * @param bool $success Успех шага.
     * @param string|null $failure Режим провала процесса.
     *
     * @return array<string, mixed> Новые счётчики.
     */
    public function countersAfterStep(
        ProcessStep $step,
        array $counters,
        bool $success,
        ?string $failure,
    ): array {
        if ($success) {
            $ops = $step->getCounterOnSuccess();
        } elseif ($failure === 'restart_from_first') {
            $ops = $step->getCounterOnFailure();
        } else {
            $ops = [];
        }

        foreach ($ops as $op) {
            $code = $op->getCounterCode();
            $current = $counters[$code] ?? 0;
            $counters[$code] = $op->apply(is_int($current) ? $current : 0);
        }

        return $counters;
    }



    /**
     * Цена шага в ресурсах.
     *
     * @param ProcessStep $step Шаг.
     *
     * @return array<int, ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если цена не целая.
     */
    public function costs(ProcessStep $step): array
    {
        return array_map(
            fn (StepCost $cost): ResourceSpend => new ResourceSpend(
                $cost->getResourceCode(),
                new ScalarResourceValue($this->amount($cost)),
            ),
            $step->getCosts(),
        );
    }

    /**
     * Заменяет цену карточки ценой шага.
     *
     * @param array<int, ResourceSpend> $spends Траты карточки.
     * @param ProcessStep $step Шаг.
     *
     * @return array<int, ResourceSpend> Траты шага.
     */
    public function replaceCosts(array $spends, ProcessStep $step): array
    {
        $codes = [];
        foreach ($step->getCosts() as $cost) {
            $codes[$cost->getResourceCode()] = true;
        }

        $kept = [];
        foreach ($spends as $spend) {
            if (!array_key_exists($spend->getResourceCode(), $codes)) {
                $kept[] = $spend;
            }
        }

        return [...$kept, ...$this->costs($step)];
    }

    /**
     * Цена шага в ОД.
     *
     * @param ProcessStep $step Шаг.
     *
     * @return int Сумма.
     *
     * @throws GameInvalidException Если цена не целая.
     */
    public function actionPoints(ProcessStep $step): int
    {
        $costs = array_filter(
            $step->getCosts(),
            static fn (StepCost $cost): bool => $cost->getResourceCode() === 'action-points',
        );

        return array_sum(array_map(fn (StepCost $cost): int => $this->amount($cost), $costs));
    }

    /**
     * Индекс текущего шага.
     *
     * @param array<int, ProcessStep> $steps Шаги.
     * @param string $current Текущий код.
     *
     * @return int Индекс.
     *
     * @throws GameInvalidException Если шага нет.
     */
    private function currentIndex(array $steps, string $current): int
    {
        $codes = array_map(static fn (ProcessStep $step): string => $step->getCode(), $steps);
        $index = array_search($current, $codes, true);
        if ($index === false) {
            throw new GameInvalidException('Game process step is invalid');
        }

        return (int) $index;
    }

    /**
     * Коды перехода из текущего шага.
     *
     * @param ProcessSpec $spec Тело.
     * @param array<int, ProcessStep> $steps Шаги.
     * @param int $index Текущий индекс.
     * @param string $current Текущий код.
     *
     * @return array<int, string> Коды.
     */
    private function transitionCodes(ProcessSpec $spec, array $steps, int $index, string $current): array
    {
        $transition = $spec->getTransition();
        if ($transition instanceof ChainTransition) {
            return $this->chainSteps($steps, $index, $transition);
        }

        if ($transition instanceof CustomTransition) {
            return $this->customSteps($transition, $current);
        }

        return $this->otherSteps($steps, $current);
    }

    /**
     * Коды рёбер произвольного перехода.
     *
     * @param CustomTransition $transition Переход.
     * @param string $current Текущий код.
     *
     * @return array<int, string> Коды.
     */
    private function customSteps(CustomTransition $transition, string $current): array
    {
        $edges = array_filter(
            $transition->getEdges(),
            static fn (TransitionEdge $edge): bool => $edge->getFrom() === $current,
        );

        return array_values(array_map(static fn (TransitionEdge $edge): string => $edge->getTo(), $edges));
    }

    /**
     * Коды остальных шагов при переходе any.
     *
     * @param array<int, ProcessStep> $steps Шаги.
     * @param string $current Текущий код.
     *
     * @return array<int, string> Коды.
     */
    private function otherSteps(array $steps, string $current): array
    {
        $others = array_filter($steps, static fn (ProcessStep $step): bool => $step->getCode() !== $current);

        return array_values(array_map(static fn (ProcessStep $step): string => $step->getCode(), $others));
    }

    /**
     * Отбрасывает шаги с невыполненным порогом.
     *
     * @param ProcessSpec $spec Тело.
     * @param array<int, string> $codes Коды.
     * @param array<string, mixed> $counters Счётчики.
     *
     * @return array<int, string> Доступные коды.
     */
    private function filterByCounters(ProcessSpec $spec, array $codes, array $counters): array
    {
        $steps = [];
        foreach ($spec->getSteps() as $step) {
            $steps[$step->getCode()] = $step;
        }

        $kept = [];
        foreach ($codes as $code) {
            $step = $steps[$code] ?? null;
            if ($step === null || CounterThreshold::areMet($step->getRequiresCounters(), $counters)) {
                $kept[] = $code;
            }
        }

        return $kept;
    }

    /**
     * Шаги цепочки из текущего.
     *
     * @param array<int, ProcessStep> $steps Шаги.
     * @param int $index Текущий индекс.
     * @param ChainTransition $transition Переход.
     *
     * @return array<int, string> Коды.
     */
    private function chainSteps(array $steps, int $index, ChainTransition $transition): array
    {
        $shift = max(1, $transition->getMaxShift());
        $codes = $this->forwardSteps($steps, $index, $shift);
        if ($transition->getDirection() !== 'both') {
            return $codes;
        }

        return [...$codes, ...$this->backwardSteps($steps, $index, $shift)];
    }

    /**
     * Шаги вперёд по цепочке.
     *
     * @param array<int, ProcessStep> $steps Шаги.
     * @param int $index Текущий индекс.
     * @param int $shift Сдвиг.
     *
     * @return array<int, string> Коды.
     */
    private function forwardSteps(array $steps, int $index, int $shift): array
    {
        return array_map(
            static fn (ProcessStep $step): string => $step->getCode(),
            array_slice($steps, $index + 1, $shift),
        );
    }

    /**
     * Шаги назад по цепочке.
     *
     * @param array<int, ProcessStep> $steps Шаги.
     * @param int $index Текущий индекс.
     * @param int $shift Сдвиг.
     *
     * @return array<int, string> Коды.
     */
    private function backwardSteps(array $steps, int $index, int $shift): array
    {
        $slice = array_slice($steps, max(0, $index - $shift), min($shift, $index));

        return array_map(static fn (ProcessStep $step): string => $step->getCode(), array_reverse($slice));
    }

    /**
     * Целое количество цены.
     *
     * @param StepCost $cost Цена.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если цена дробная.
     */
    private function amount(StepCost $cost): int
    {
        $amount = $cost->getAmount();
        if (is_int($amount)) {
            return max(0, $amount);
        }

        if ($amount->getSize() === 0) {
            return max(0, $amount->getBase());
        }

        throw new GameInvalidException('Game process step cost is invalid');
    }
}
