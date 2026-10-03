<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ActionComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ChosenAmount;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\MaterialComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\SomaticComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\VerbalComponent;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Компоненты действия и заклинания.
 */
final class ActionComponents
{
    /**
     * Список компонентов.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, ActionComponent> Компоненты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): array
    {
        $components = [];
        foreach (SpecShape::list($document, 'components') as $row) {
            $components[] = self::one($row);
        }

        return $components;
    }

    /**
     * Один компонент.
     *
     * @param mixed $row Строка.
     *
     * @return ActionComponent Компонент.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function one(mixed $row): ActionComponent
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('components');
        }

        $type = SpecShape::string($row, 'type');

        return match ($type) {
            'resource' => new ResourceComponent(
                SpecShape::string($row, 'resource_code'),
                self::amount($row),
                SpecShape::optionalString($row, 'label'),
            ),
            'verbal' => new VerbalComponent(SpecShape::optionalString($row, 'note')),
            'somatic' => new SomaticComponent(
                SpecShape::optionalString($row, 'note'),
                SpecShape::optionalInt($row, 'occupy_hands'),
            ),
            'material' => new MaterialComponent(
                SpecShape::string($row, 'mode'),
                SpecShape::optionalString($row, 'item_code'),
                SpecShape::stringList($row, 'keyword_codes'),
                SpecShape::optionalString($row, 'description'),
            ),
            default => throw new RuleSpecShapeException('components'),
        };
    }

    /**
     * Количество ресурса.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int|DimensionalNumber|ChosenAmount Количество.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function amount(array $row): int|DimensionalNumber|ChosenAmount
    {
        $amount = $row['amount'] ?? 0;
        if (is_int($amount)) {
            return $amount;
        }

        if (!is_array($amount) || array_is_list($amount)) {
            throw new RuleSpecShapeException('amount');
        }

        if (($amount['type'] ?? null) === 'chosen') {
            return new ChosenAmount();
        }

        return DimensionalNumbers::pair($amount, 'amount');
    }
}
