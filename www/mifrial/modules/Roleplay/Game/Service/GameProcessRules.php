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
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

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
        foreach ($spec->getSteps() as $step) {
            if ($step->getCode() === $code) {
                return $step;
            }
        }

        throw new GameInvalidException('Game process step is invalid');
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
        $index = null;
        foreach ($steps as $offset => $step) {
            if ($step->getCode() === $current) {
                $index = $offset;
                break;
            }
        }

        if ($index === null) {
            throw new GameInvalidException('Game process step is invalid');
        }

        $transition = $spec->getTransition();
        $codes = [];
        if ($transition instanceof ChainTransition) {
            $codes = $this->chainSteps($steps, $index, $transition);
        } elseif ($transition instanceof CustomTransition) {
            foreach ($transition->getEdges() as $edge) {
                if ($edge->getFrom() === $current) {
                    $codes[] = $edge->getTo();
                }
            }
        } else {
            foreach ($steps as $step) {
                if ($step->getCode() !== $current) {
                    $codes[] = $step->getCode();
                }
            }
        }

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
        $spends = [];
        foreach ($step->getCosts() as $cost) {
            $spends[] = new ResourceSpend(
                $cost->getResourceCode(),
                new ScalarResourceValue($this->amount($cost)),
            );
        }

        return $spends;
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
        $sum = 0;
        foreach ($step->getCosts() as $cost) {
            if ($cost->getResourceCode() === 'action-points') {
                $sum += $this->amount($cost);
            }
        }

        return $sum;
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
        $codes = [];
        $shift = max(1, $transition->getMaxShift());
        for ($offset = 1; $offset <= $shift; ++$offset) {
            $next = $index + $offset;
            if (array_key_exists($next, $steps)) {
                $codes[] = $steps[$next]->getCode();
            }
        }

        if ($transition->getDirection() === 'both') {
            for ($offset = 1; $offset <= $shift; ++$offset) {
                $previous = $index - $offset;
                if (array_key_exists($previous, $steps)) {
                    $codes[] = $steps[$previous]->getCode();
                }
            }
        }

        return $codes;
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

        if ($amount instanceof DimensionalNumber && $amount->getSize() === 0) {
            return max(0, $amount->getBase());
        }

        throw new GameInvalidException('Game process step cost is invalid');
    }
}
