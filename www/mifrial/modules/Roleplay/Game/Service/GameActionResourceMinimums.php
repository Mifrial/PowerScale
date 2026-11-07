<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\DimensionalResourceValue;
use Mifrial\Roleplay\Character\Dto\ResourceValue;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinActionCostOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinResourceCostOp;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperationWhen;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Минимумы ресурсов из модификаторов выбранного предмета.
 */
final class GameActionResourceMinimums
{
    /**
     * Создаёт расчёт минимумов.
     *
     * @param CharacterResourceArithmetic $arithmetic Арифметика значений.
     *
     * @return void
     */
    public function __construct(private readonly CharacterResourceArithmetic $arithmetic)
    {
    }

    /**
     * Максимальные минимумы по ресурсам.
     *
     * @param CharacterRuleSlice $slice Live revision slice.
     * @param int $itemInventoryId Selected inventory row.
     * @param string $itemRuleCode Item rule code.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param array<int, ResourceComponent> $components Ресурсные компоненты.
     *
     * @return array<string, ResourceValue> Maximum minimums by resource.
     *
     * @throws GameInvalidException If a modifier is invalid.
     */
    public function of(
        CharacterRuleSlice $slice,
        int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $components,
    ): array {
        $item = $this->item($slice, $itemRuleCode);
        $row = $this->row($choices, $itemInventoryId, $itemRuleCode);
        $minimums = [];
        foreach ($this->modifierCodes($row) as $code) {
            $this->applyModifier($slice, $code, $item, $components, $minimums);
        }

        return $minimums;
    }

    /**
     * Живая карточка предмета.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $itemRuleCode Код предмета.
     *
     * @return CharacterResolvedRule Карточка.
     *
     * @throws GameInvalidException Если предмета нет.
     */
    private function item(CharacterRuleSlice $slice, string $itemRuleCode): CharacterResolvedRule
    {
        $item = $slice->findLive($itemRuleCode);
        if ($item === null || $item->isSpecBroken()) {
            throw new GameInvalidException('Game action item is invalid');
        }

        return $item;
    }

    /**
     * Надетый выбранный предмет.
     *
     * @param array<string, mixed> $choices Выбор.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Код предмета.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если строка отсутствует или не та.
     */
    private function row(array $choices, int $itemInventoryId, string $itemRuleCode): array
    {
        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game action inventory is invalid');
        }

        $selected = array_values(array_filter(
            $inventory,
            static fn (mixed $row): bool => is_array($row) && ($row['id'] ?? null) === $itemInventoryId,
        ));
        if (count($selected) !== 1) {
            throw new GameInvalidException('Game action inventory item is missing or ambiguous');
        }

        $row = $selected[0];
        if (($row['equipped'] ?? false) !== true || ($row['ruleCode'] ?? null) !== $itemRuleCode) {
            throw new GameInvalidException('Game action inventory item is invalid');
        }

        return $row;
    }

    /**
     * Коды модификаторов строки.
     *
     * @param array<string, mixed> $row Строка инвентаря.
     *
     * @return array<int, mixed> Коды.
     *
     * @throws GameInvalidException Если карта битая.
     */
    private function modifierCodes(array $row): array
    {
        $codes = $row['modifiers'] ?? [];
        if (!is_array($codes)) {
            throw new GameInvalidException('Game action modifiers are invalid');
        }

        return $codes;
    }

    /**
     * Минимумы одного модификатора.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код модификатора.
     * @param CharacterResolvedRule $item Предмет.
     * @param array<int, ResourceComponent> $components Компоненты.
     * @param array<string, ResourceValue> $minimums Минимумы.
     *
     * @return void
     *
     * @throws GameInvalidException Если модификатор битый.
     */
    private function applyModifier(
        CharacterRuleSlice $slice,
        string $code,
        CharacterResolvedRule $item,
        array $components,
        array &$minimums,
    ): void {
        $modifier = $slice->findLive($code);
        $spec = $modifier?->getSpec();
        if ($modifier === null || $modifier->isSpecBroken() || !$spec instanceof ItemModifierSpec) {
            throw new GameInvalidException('Game action modifier is invalid');
        }

        foreach ($spec->getOperations() as $operation) {
            if (!ItemModifierOperationWhen::matches($operation->getWhen(), $item->getKeywordCodes())) {
                continue;
            }

            $this->applyOperation($slice, $operation->getOp(), $components, $minimums);
        }
    }

    /**
     * Минимум одной операции.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $op Операция.
     * @param array<int, ResourceComponent> $components Компоненты.
     * @param array<string, ResourceValue> $minimums Минимумы.
     *
     * @return void
     *
     * @throws GameInvalidException Если операция неоднозначна.
     */
    private function applyOperation(
        CharacterRuleSlice $slice,
        mixed $op,
        array $components,
        array &$minimums,
    ): void {
        if ($op instanceof MinActionCostOp) {
            $this->applyActionMinimum($slice, $op, $components, $minimums);

            return;
        }

        if ($op instanceof MinResourceCostOp) {
            $this->addMinimum($slice, $op->getResourceCode(), $op->getMinimum(), $minimums);
        }
    }

    /**
     * Минимум старой формы цены действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param MinActionCostOp $op Операция.
     * @param array<int, ResourceComponent> $components Компоненты.
     * @param array<string, ResourceValue> $minimums Минимумы.
     *
     * @return void
     *
     * @throws GameInvalidException Если компонент не один.
     */
    private function applyActionMinimum(
        CharacterRuleSlice $slice,
        MinActionCostOp $op,
        array $components,
        array &$minimums,
    ): void {
        if (count($components) !== 1) {
            throw new GameInvalidException('Legacy action cost mapping is ambiguous');
        }

        $this->addMinimum($slice, $components[0]->getResourceCode(), $op->getMin(), $minimums);
    }

    /**
     * Добавляет минимум с максимумом по коду.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $resourceCode Код ресурса.
     * @param int|DimensionalNumber $value Native значение.
     * @param array<string, ResourceValue> $minimums Минимумы.
     *
     * @return void
     *
     * @throws GameInvalidException Если значение не той формы.
     */
    private function addMinimum(
        CharacterRuleSlice $slice,
        string $resourceCode,
        int|DimensionalNumber $value,
        array &$minimums,
    ): void {
        $minimum = $this->nativeValue($value, $this->resourceSpec($slice, $resourceCode));
        $minimums[$resourceCode] = isset($minimums[$resourceCode])
            ? $this->arithmetic->maxValue($minimums[$resourceCode], $minimum)
            : $minimum;
    }

    /**
     * Разрешает live ResourceSpec.
     *
     * @param CharacterRuleSlice $slice Live revision slice.
     * @param string $resourceCode Resource code.
     *
     * @return ResourceSpec Live spec.
     *
     * @throws GameInvalidException If resource is missing or broken.
     */
    private function resourceSpec(CharacterRuleSlice $slice, string $resourceCode): ResourceSpec
    {
        $rule = $slice->findLive($resourceCode);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ResourceSpec) {
            throw new GameInvalidException('Game action resource rule is invalid');
        }

        return $spec;
    }

    /**
     * Преобразует native minimum в typed value.
     *
     * @param int|DimensionalNumber $value Native value.
     * @param ResourceSpec $spec Live resource spec.
     *
     * @return ResourceValue Typed value.
     *
     * @throws GameInvalidException If the variants differ.
     */
    private function nativeValue(int|DimensionalNumber $value, ResourceSpec $spec): ResourceValue
    {
        try {
            if ($spec->isDimensional() && $value instanceof DimensionalNumber) {
                return new DimensionalResourceValue($value);
            }

            if (!$spec->isDimensional() && is_int($value)) {
                return new ScalarResourceValue($value);
            }
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action minimum is invalid', $exception);
        }

        throw new GameInvalidException('Game action minimum shape is invalid');
    }
}
