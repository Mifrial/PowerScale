<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\MovementAxisDistance;
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
        $row = SpecShape::requireMap($row, 'steps');

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
            $operation = SpecShape::requireMap($operation, 'operations');

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
            'movement' => self::movement($operation),
            'turn' => new TurnOperation(SpecShape::int($operation, 'max_degrees')),
            'posture' => new PostureOperation(SpecShape::string($operation, 'posture')),
            default => throw new RuleSpecShapeException('operations'),
        };
    }

    /**
     * Операция movement: направления и вилка DimensionalFormula по осям.
     *
     * @param array<string, mixed> $operation Строка.
     *
     * @return MovementOperation Операция.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function movement(array $operation): MovementOperation
    {
        $directions = SpecShape::object($operation, 'allowedDirections') ?? [];
        $distance = SpecShape::object($operation, 'distance') ?? [];
        $turn = SpecShape::object($operation, 'freeTurn') ?? [];

        return new MovementOperation(
            SpecShape::stringList($directions, 'horizontal'),
            SpecShape::stringList($directions, 'vertical'),
            self::axis($distance, 'horizontal'),
            self::axis($distance, 'vertical'),
            SpecShape::optionalInt($turn, 'maxDegrees'),
            SpecShape::bool($turn, 'free'),
        );
    }

    /**
     * Вилка дистанции по оси.
     *
     * @param array<string, mixed> $document Объект distance.
     * @param string $key Ось.
     *
     * @return MovementAxisDistance|null Вилка или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function axis(array $document, string $key): ?MovementAxisDistance
    {
        $row = SpecShape::object($document, $key);
        if ($row === null) {
            return null;
        }

        return new MovementAxisDistance(
            Formulas::dimensional($row, 'min'),
            Formulas::dimensional($row, 'max'),
            SpecShape::bool($row, 'minInclusive'),
            SpecShape::bool($row, 'maxInclusive'),
        );
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
