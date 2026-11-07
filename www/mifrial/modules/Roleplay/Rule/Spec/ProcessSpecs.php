<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessCounter;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessExecution;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StepCost;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ChainTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\CustomTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\FreeTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\TransitionEdge;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Тело процесса.
 */
final class ProcessSpecs
{
    /**
     * Читает process. Нет ключа — пустой переход free.
     *
     * @param array<string|int, mixed> $document Документ способности.
     *
     * @return ProcessSpec Тело.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): ProcessSpec
    {
        $process = SpecShape::object($document, 'process') ?? [];
        $counters = self::counters($process);
        $steps = self::steps($process, array_map(
            static fn (ProcessCounter $counter): string => $counter->getCode(),
            $counters,
        ));
        $startStepCode = SpecShape::string($process, 'start_step_code');
        if ($startStepCode === '') {
            $startStepCode = $steps[0]->getCode();
        }

        return new ProcessSpec(
            $steps,
            $startStepCode,
            SpecShape::stringList($process, 'exit_step_codes'),
            self::transition($process),
            SpecShape::optionalString($process, 'failure'),
            ActionEffects::list($process, 'completion_effects'),
            SpecShape::bool($process, 'repeat_weapon_circumstance'),
            $counters,
            self::targetMode($process),
        );
    }

    /**
     * Счётчики процесса. Нет ключа — пустой список, дубль кода — отказ.
     *
     * @param array<string, mixed> $process Тело.
     *
     * @return array<int, ProcessCounter> Счётчики.
     *
     * @throws RuleSpecShapeException Если форма чужая или код повторён.
     */
    private static function counters(array $process): array
    {
        $counters = [];
        $codes = [];
        foreach (SpecShape::list($process, 'counters') as $row) {
            $row = SpecShape::requireMap($row, 'counters');
            $code = ProcessShape::requiredString($row, 'counter_code', 'counters');
            if (isset($codes[$code])) {
                throw new RuleSpecShapeException('counters');
            }

            $codes[$code] = true;
            $counters[] = new ProcessCounter($code, SpecShape::optionalInt($row, 'initial') ?? 0);
        }

        return $counters;
    }

    /**
     * Режим цели. Нет ключа — any.
     *
     * @param array<string, mixed> $process Тело.
     *
     * @return string any или single.
     *
     * @throws RuleSpecShapeException Если режим неизвестен.
     */
    private static function targetMode(array $process): string
    {
        $mode = SpecShape::string($process, 'target_mode');
        if ($mode === '') {
            return ProcessSpec::TARGET_ANY;
        }

        if ($mode !== ProcessSpec::TARGET_ANY && $mode !== ProcessSpec::TARGET_SINGLE) {
            throw new RuleSpecShapeException('target_mode');
        }

        return $mode;
    }

    /**
     * Шаги. Каждый шаг обязан быть объектом.
     *
     * @param array<string, mixed> $process Тело.
     * @param array<int, string> $counterCodes Объявленные счётчики.
     *
     * @return array<int, ProcessStep> Шаги.
     *
     * @throws RuleSpecShapeException Если шаг чужой.
     */
    private static function steps(array $process, array $counterCodes): array
    {
        $steps = [];
        foreach (SpecShape::list($process, 'steps') as $step) {
            $steps[] = self::step($step, $counterCodes);
        }

        return $steps;
    }

    /**
     * Один шаг. Нет прерывания — normal и пустые эффекты.
     *
     * @param mixed $row Строка.
     * @param array<int, string> $counterCodes Объявленные счётчики.
     *
     * @return ProcessStep Шаг.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function step(mixed $row, array $counterCodes): ProcessStep
    {
        $row = SpecShape::requireMap($row, 'steps');

        $interruption = SpecShape::object($row, 'interruption');
        $execution = self::execution($row);

        return new ProcessStep(
            SpecShape::string($row, 'code'),
            SpecShape::string($row, 'name'),
            SpecShape::string($row, 'description'),
            self::costs($row),
            $interruption === null ? 'normal' : SpecShape::string($interruption, 'mode'),
            $interruption === null ? [] : ActionEffects::list($interruption, 'effects'),
            ProcessOperations::read($row),
            $execution,
            self::strike($row, $execution),
            ProcessCounters::readThresholds($row, 'requires_counters', $counterCodes),
            ProcessCounters::counterOps($row, 'counter_on_success', $counterCodes),
            ProcessCounters::counterOps($row, 'counter_on_failure', $counterCodes),
        );
    }

    /**
     * Пакет удара шага. Только для шага-удара.
     *
     * @param array<string, mixed> $row Шаг.
     * @param ProcessExecution $execution Режим исполнения.
     *
     * @return StrikeModifier|null Пакет или null.
     *
     * @throws RuleSpecShapeException Если пакет у неатакующего шага.
     */
    private static function strike(array $row, ProcessExecution $execution): ?StrikeModifier
    {
        if (SpecShape::object($row, 'strike') === null) {
            return null;
        }

        if (!$execution->isStrike()) {
            throw new RuleSpecShapeException('strike');
        }

        return ProcessCounters::readStrikeModifier($row, 'strike');
    }

    /**
     * Режим исполнения шага.
     *
     * Нет ключа execution: шаг с ключом operations (даже пустым) —
     * операционный, без ключа — удар. Это совместимость ревизии 27.
     *
     * @param array<string, mixed> $row Шаг.
     *
     * @return ProcessExecution Режим.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function execution(array $row): ProcessExecution
    {
        $execution = SpecShape::object($row, 'execution');
        if ($execution === null) {
            return array_key_exists('operations', $row)
                ? new ProcessExecution(ProcessExecution::TYPE_NONE)
                : new ProcessExecution(ProcessExecution::TYPE_STRIKE);
        }

        $type = SpecShape::string($execution, 'type');
        if (
            $type !== ProcessExecution::TYPE_STRIKE
            && $type !== ProcessExecution::TYPE_ACTION
            && $type !== ProcessExecution::TYPE_NONE
        ) {
            throw new RuleSpecShapeException('execution');
        }

        return new ProcessExecution($type, SpecShape::optionalString($execution, 'action_code'));
    }

    /**
     * Цены шага.
     *
     * @param array<string, mixed> $row Шаг.
     *
     * @return array<int, StepCost> Цены.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function costs(array $row): array
    {
        $costs = [];
        foreach (SpecShape::list($row, 'costs') as $cost) {
            $cost = SpecShape::requireMap($cost, 'costs');

            $costs[] = new StepCost(SpecShape::string($cost, 'resource_code'), self::amount($cost));
        }

        return $costs;
    }

    /**
     * Количество цены.
     *
     * @param array<string, mixed> $cost Цена.
     *
     * @return int|DimensionalNumber Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function amount(array $cost): int|DimensionalNumber
    {
        $amount = $cost['amount'] ?? 0;
        if (is_int($amount)) {
            return $amount;
        }

        $amount = SpecShape::requireMap($amount, 'amount');

        return DimensionalNumbers::pair($amount, 'amount');
    }

    /**
     * Переход. Нет ключа — free.
     *
     * @param array<string, mixed> $process Тело.
     *
     * @return ProcessTransition Переход.
     *
     * @throws RuleSpecShapeException Если режим неизвестен.
     */
    private static function transition(array $process): ProcessTransition
    {
        $transition = SpecShape::object($process, 'transition');
        if ($transition === null) {
            return new FreeTransition();
        }

        $mode = SpecShape::string($transition, 'mode');

        return match ($mode) {
            'free' => new FreeTransition(),
            'chain' => new ChainTransition(SpecShape::int($transition, 'max_shift'), SpecShape::optionalString($transition, 'direction')),
            'custom' => new CustomTransition(self::edges($transition), SpecShape::stringList($transition, 'exits')),
            default => throw new RuleSpecShapeException('transition'),
        };
    }

    /**
     * Рёбра своего графа.
     *
     * @param array<string, mixed> $transition Переход.
     *
     * @return array<int, TransitionEdge> Рёбра.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function edges(array $transition): array
    {
        $edges = [];
        foreach (SpecShape::list($transition, 'edges') as $edge) {
            $edge = SpecShape::requireMap($edge, 'edges');

            $edges[] = new TransitionEdge(SpecShape::string($edge, 'from'), SpecShape::string($edge, 'to'));
        }

        return $edges;
    }
}
