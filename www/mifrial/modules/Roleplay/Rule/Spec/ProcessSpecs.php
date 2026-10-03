<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\MovementOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\PostureOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\ProcessOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\TurnOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ChainTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\CustomTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\FreeTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\TransitionEdge;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StepCost;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

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

        return new ProcessSpec(
            self::steps($process),
            SpecShape::string($process, 'start_step_code'),
            SpecShape::stringList($process, 'exit_step_codes'),
            self::transition($process),
            SpecShape::optionalString($process, 'failure'),
            ActionEffects::list($process, 'completion_effects'),
            SpecShape::bool($process, 'repeat_weapon_circumstance'),
        );
    }

    /**
     * Шаги. Каждый шаг обязан быть объектом.
     *
     * @param array<string, mixed> $process Тело.
     *
     * @return array<int, ProcessStep> Шаги.
     *
     * @throws RuleSpecShapeException Если шаг чужой.
     */
    private static function steps(array $process): array
    {
        $steps = [];
        foreach (SpecShape::list($process, 'steps') as $step) {
            $steps[] = self::step($step);
        }

        return $steps;
    }

    /**
     * Один шаг. Нет прерывания — normal и пустые эффекты.
     *
     * @param mixed $row Строка.
     *
     * @return ProcessStep Шаг.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function step(mixed $row): ProcessStep
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('steps');
        }

        $interruption = SpecShape::object($row, 'interruption');

        return new ProcessStep(
            SpecShape::string($row, 'code'),
            SpecShape::string($row, 'name'),
            SpecShape::string($row, 'description'),
            self::costs($row),
            $interruption === null ? 'normal' : SpecShape::string($interruption, 'mode'),
            $interruption === null ? [] : ActionEffects::list($interruption, 'effects'),
            self::operations($row),
        );
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
            if (!is_array($cost) || array_is_list($cost)) {
                throw new RuleSpecShapeException('costs');
            }

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

        if (!is_array($amount) || array_is_list($amount)) {
            throw new RuleSpecShapeException('amount');
        }

        return DimensionalNumbers::pair($amount, 'amount');
    }

    /**
     * Операции шага.
     *
     * @param array<string, mixed> $row Шаг.
     *
     * @return array<int, ProcessOperation> Операции.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function operations(array $row): array
    {
        $operations = [];
        foreach (SpecShape::list($row, 'operations') as $operation) {
            if (!is_array($operation) || array_is_list($operation)) {
                throw new RuleSpecShapeException('operations');
            }

            $operations[] = self::operation($operation);
        }

        return $operations;
    }

    /**
     * Операции действия или заклинания.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, ProcessOperation> Операции.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function readOperations(array $document): array
    {
        return self::operations($document);
    }

    /**
     * Одна операция.
     *
     * @param array<string, mixed> $operation Строка.
     *
     * @return ProcessOperation Операция.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    private static function operation(array $operation): ProcessOperation
    {
        $type = SpecShape::string($operation, 'type');

        return match ($type) {
            'movement' => new MovementOperation(
                SpecShape::stringList($operation, 'horizontal'),
                SpecShape::stringList($operation, 'vertical'),
                ProcessDistances::optional($operation, 'distance'),
                SpecShape::optionalInt($operation, 'max_degrees'),
                SpecShape::bool($operation, 'free'),
            ),
            'turn' => new TurnOperation(SpecShape::int($operation, 'max_degrees')),
            'posture' => new PostureOperation(SpecShape::string($operation, 'posture')),
            default => throw new RuleSpecShapeException('operations'),
        };
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
            if (!is_array($edge) || array_is_list($edge)) {
                throw new RuleSpecShapeException('edges');
            }

            $edges[] = new TransitionEdge(SpecShape::string($edge, 'from'), SpecShape::string($edge, 'to'));
        }

        return $edges;
    }
}
