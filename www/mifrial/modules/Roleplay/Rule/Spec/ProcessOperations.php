<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\MovementAxisDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\MovementOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\PostureOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\ProcessOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\TurnOperation;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Операции шага: movement, turn и posture.
 */
final class ProcessOperations
{
    /**
     * Операции действия или заклинания.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, ProcessOperation> Операции.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): array
    {
        return self::operations($document);
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
}
