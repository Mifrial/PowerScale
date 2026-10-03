<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\AddDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\ChangeSizeDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\CurrentMovementStepDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\LiteralDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\ProcessDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\SizeGapTimesStepDistance;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\StepsDistance;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Дистанция операции процесса.
 */
final class ProcessDistances
{
    /**
     * Дистанция. Нет ключа — null.
     *
     * @param array<string, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return ProcessDistance|null Узел или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function optional(array $document, string $key): ?ProcessDistance
    {
        $value = SpecShape::object($document, $key);

        return $value === null ? null : self::read($value);
    }

    /**
     * Узел дистанции.
     *
     * @param array<string, mixed> $node Узел.
     *
     * @return ProcessDistance Дистанция.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    public static function read(array $node): ProcessDistance
    {
        $type = SpecShape::string($node, 'type');

        return match ($type) {
            'steps' => new StepsDistance(SpecShape::int($node, 'count')),
            'literal' => new LiteralDistance(DimensionalNumbers::required($node, 'value')),
            'current_movement_step' => new CurrentMovementStepDistance(SpecShape::int($node, 'multiplier')),
            'size_gap_times_step' => new SizeGapTimesStepDistance(
                SpecShape::string($node, 'characteristic_code_from'),
                SpecShape::string($node, 'characteristic_code_to'),
                SpecShape::int($node, 'base_steps'),
                SpecShape::int($node, 'gap_multiplier'),
            ),
            'change_size' => self::changeSize($node),
            'add' => self::add($node),
            default => throw new RuleSpecShapeException('distance'),
        };
    }

    /**
     * Сдвиг размера вложенной дистанции.
     *
     * @param array<string, mixed> $node Узел.
     *
     * @return ChangeSizeDistance Дистанция.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function changeSize(array $node): ChangeSizeDistance
    {
        $inner = SpecShape::object($node, 'expression') ?? [];

        return new ChangeSizeDistance(self::read($inner), SpecShape::int($node, 'size_delta'));
    }

    /**
     * Сумма дистанций.
     *
     * @param array<string, mixed> $node Узел.
     *
     * @return AddDistance Дистанция.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function add(array $node): AddDistance
    {
        $parts = [];
        foreach (SpecShape::list($node, 'expressions') as $part) {
            if (!is_array($part) || array_is_list($part)) {
                throw new RuleSpecShapeException('expressions');
            }

            $parts[] = self::read($part);
        }

        return new AddDistance($parts);
    }
}
