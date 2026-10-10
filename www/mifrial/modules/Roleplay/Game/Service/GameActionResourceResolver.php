<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\DimensionalResourceValue;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ResourceValue;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ChosenAmount;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinActionCostOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinResourceCostOp;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperationWhen;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разрешает live action resources до вызова mutation boundary.
 */
final class GameActionResourceResolver
{
    private readonly CharacterResourceStorage $storage;

    private readonly CharacterResourceArithmetic $arithmetic;

    /**
     * Создаёт resolver.
     *
     * @return void
     */
    public function __construct()
    {
        $this->storage = new CharacterResourceStorage();
        $this->arithmetic = new CharacterResourceArithmetic();
    }

    /**
     * Разрешает native spends и effective minimums.
     *
     * @param CharacterRuleSlice $slice Live revision slice.
     * @param string $actionRuleCode Action rule code.
     * @param int $itemInventoryId Selected inventory row.
     * @param string $itemRuleCode Item rule code.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param array<string, mixed> $chosenAmounts Server-owned chosen amounts.
     *
     * @return array<int, ResourceSpend> Resolved native spends.
     *
     * @throws GameInvalidException If the action, resource or choice is invalid.
     */
    public function resolve(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $sheet,
        array $chosenAmounts = [],
    ): array {
        $action = $slice->findLive($actionRuleCode);
        $spec = $action?->getSpec();
        if ($action === null || $action->isSpecBroken()) {
            throw new GameInvalidException('Game action rule is invalid');
        }
        if (!$spec instanceof AbilityActionSpec) {
            return [];
        }

        $components = [];
        foreach ($spec->getComponents() as $component) {
            if ($component instanceof ResourceComponent) {
                $components[] = $component;
            }
        }
        $this->assertChosenAmounts($chosenAmounts, $components);
        if ($components === []) {
            return [];
        }

        $minimums = $this->minimums($slice, $itemInventoryId, $itemRuleCode, $choices, $components);
        $spends = [];
        foreach ($components as $component) {
            $resourceSpec = $this->resourceSpec($slice, $component->getResourceCode());
            $amount = $this->amount(
                $component,
                $chosenAmounts,
                $resourceSpec,
                $this->currentScalar($sheet, $component->getResourceCode(), $slice, $component),
            );
            $minimum = $minimums[$component->getResourceCode()] ?? null;
            if ($minimum instanceof ResourceValue) {
                $amount = $this->arithmetic->maxValue($amount, $minimum);
            }

            $spends[] = new ResourceSpend($component->getResourceCode(), $amount);
        }

        return $spends;
    }

    /**
     * Разрешает reaction action по его live combat_action semantic.
     *
     * @param CharacterRuleSlice $slice Live revision slice.
     * @param string $reaction Reaction code.
     * @param int|null $itemInventoryId Selected block inventory row.
     * @param string $itemRuleCode Selected block item.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param array<string, mixed> $chosenAmounts Server-owned choices.
     *
     * @return array<int, ResourceSpend> Resolved native spends.
     *
     * @throws GameInvalidException If action is missing or ambiguous.
     */
    public function resolveReaction(
        CharacterRuleSlice $slice,
        string $reaction,
        ?int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $sheet,
        array $chosenAmounts = [],
    ): array {
        $codes = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if (!$spec instanceof AbilityActionSpec || $rule->isSpecBroken()) {
                continue;
            }

            if ($spec->getBase()->getCombatAction() === $reaction) {
                $codes[] = $rule->getCode();
            }
        }

        if ($codes === []) {
            return [];
        }
        if (count($codes) !== 1) {
            throw new GameInvalidException('Game reaction action is missing or ambiguous');
        }

        return $this->resolve(
            $slice,
            $codes[0],
            $itemInventoryId ?? 0,
            $itemRuleCode,
            $choices,
            $sheet,
            $chosenAmounts,
        );
    }

    /**
     * Проверяет current уже resolved spends без mutation.
     *
     * @param CharacterRuleSlice $slice Live revision slice.
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param array<int, ResourceSpend> $spends Prepared spends.
     *
     * @return bool true, если current достаточен.
     *
     * @throws GameInvalidException If storage is invalid.
     */
    public function hasSufficientCurrent(
        CharacterRuleSlice $slice,
        array $sheet,
        array $spends,
    ): bool {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new GameInvalidException('Game action resources require backfill');
        }

        try {
            $typed = $this->storage->parseRows($rows, $slice);

            return $this->storage->canSpendRows($typed, $slice, $spends);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resources are invalid', $exception);
        }
    }

    /**
     * Возвращает native value из компонента.
     *
     * @param ResourceComponent $component Компонент.
     * @param array<string, mixed> $chosenAmounts Server choices.
     * @param ResourceSpec $spec Live resource spec.
     * @param ScalarResourceValue|null $current Current scalar value.
     *
     * @return ResourceValue Typed native amount.
     *
     * @throws GameInvalidException If choice or shape is invalid.
     */
    private function amount(
        ResourceComponent $component,
        array $chosenAmounts,
        ResourceSpec $spec,
        ?ScalarResourceValue $current,
    ): ResourceValue {
        $amount = $component->getAmount();
        if ($amount instanceof ChosenAmount) {
            $amount = $chosenAmounts[$component->getResourceCode()] ?? null;
        }

        try {
            if ($spec->isDimensional() && is_array($amount) && !array_is_list($amount)) {
                return new DimensionalResourceValue(new DimensionalNumber(
                    $this->integer($amount, 'base'),
                    $this->integer($amount, 'size'),
                ));
            }

            if (!$spec->isDimensional() && is_int($amount)) {
                if ($component->getAmount() instanceof ChosenAmount
                    && ($amount < 1 || $current === null || $amount > $current->getValue())
                ) {
                    throw new GameInvalidException('Game chosen resource amount is out of range');
                }
                return new ScalarResourceValue($amount);
            }
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resource amount is invalid', $exception);
        }

        throw new GameInvalidException('Game action resource shape is invalid');
    }

    /**
     * Читает current scalar для выбранной суммы.
     *
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param string $resourceCode Resource code.
     * @param CharacterRuleSlice $slice Live slice.
     * @param ResourceComponent $component Resource component.
     *
     * @return ScalarResourceValue|null Current scalar or null.
     *
     * @throws GameInvalidException If storage is invalid.
     */
    private function currentScalar(
        array $sheet,
        string $resourceCode,
        CharacterRuleSlice $slice,
        ResourceComponent $component,
    ): ?ScalarResourceValue {
        if (!$component->getAmount() instanceof ChosenAmount) {
            return null;
        }
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new GameInvalidException('Game action resources require backfill');
        }
        try {
            foreach ($this->storage->parseRows($rows, $slice) as $row) {
                if ($row->getRuleCode() !== $resourceCode) {
                    continue;
                }
                $current = $row->getCurrent();

                return $current instanceof ScalarResourceValue ? $current : null;
            }
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resources are invalid', $exception);
        }

        return null;
    }

    /**
     * Проверяет карту предложений выбранной суммы.
     *
     * @param array<string, mixed> $chosenAmounts Server proposal.
     * @param array<int, ResourceComponent> $components Live components.
     *
     * @return void
     *
     * @throws GameInvalidException If the map is malformed or ambiguous.
     */
    private function assertChosenAmounts(array $chosenAmounts, array $components): void
    {
        if ($chosenAmounts !== [] && array_is_list($chosenAmounts)) {
            throw new GameInvalidException('Game chosen resource amounts are ambiguous');
        }
        $codes = [];
        foreach ($components as $component) {
            if ($component->getAmount() instanceof ChosenAmount) {
                $codes[$component->getResourceCode()] = true;
            }
        }
        foreach ($chosenAmounts as $code => $value) {
            if (!is_string($code)
                || $code === ''
                || !isset($codes[$code])
                || (!is_int($value) && !is_array($value))
            ) {
                throw new GameInvalidException('Game chosen resource amounts are invalid');
            }
        }
    }

    /**
     * Считывает minimum операции выбранного предмета.
     *
     * @param CharacterRuleSlice $slice Live revision slice.
     * @param int $itemInventoryId Selected inventory row.
     * @param string $itemRuleCode Item rule code.
     * @param array<string, mixed> $choices Authoritative choices.
     *
     * @return array<string, ResourceValue> Maximum minimums by resource.
     *
     * @throws GameInvalidException If a modifier is invalid.
     */
    private function minimums(
        CharacterRuleSlice $slice,
        int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $components,
    ): array {
        $item = $slice->findLive($itemRuleCode);
        if ($item === null || $item->isSpecBroken()) {
            throw new GameInvalidException('Game action item is invalid');
        }

        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game action inventory is invalid');
        }

        $minimums = [];
        $selectedRows = array_values(array_filter(
            $inventory,
            static fn (mixed $row): bool => is_array($row) && ($row['id'] ?? null) === $itemInventoryId,
        ));
        if (count($selectedRows) !== 1) {
            throw new GameInvalidException('Game action inventory item is missing or ambiguous');
        }
        $row = $selectedRows[0];
        if (($row['equipped'] ?? false) !== true || ($row['ruleCode'] ?? null) !== $itemRuleCode) {
            throw new GameInvalidException('Game action inventory item is invalid');
        }

        $codes = $row['modifiers'] ?? [];
        if (!is_array($codes)) {
            throw new GameInvalidException('Game action modifiers are invalid');
        }

        foreach ($codes as $code) {
            $modifier = $slice->findLive(is_string($code) ? $code : '');
            $modifierSpec = $modifier?->getSpec();
            if ($modifier === null || $modifier->isSpecBroken() || !$modifierSpec instanceof ItemModifierSpec) {
                throw new GameInvalidException('Game action modifier is invalid');
            }

            foreach ($modifierSpec->getOperations() as $operation) {
                if (!ItemModifierOperationWhen::matches($operation->getWhen(), $item->getKeywordCodes())) {
                    continue;
                }

                $op = $operation->getOp();
                if ($op instanceof MinActionCostOp) {
                    if (count($components) !== 1 || !$components[0] instanceof ResourceComponent) {
                        throw new GameInvalidException('Legacy action cost mapping is ambiguous');
                    }

                    $resourceCode = $components[0]->getResourceCode();
                    $spec = $this->resourceSpec($slice, $resourceCode);
                    $minimum = $this->nativeValue($op->getMin(), $spec);
                    $minimums[$resourceCode] = isset($minimums[$resourceCode])
                        ? $this->arithmetic->maxValue($minimums[$resourceCode], $minimum)
                        : $minimum;
                    continue;
                }

                if (!$op instanceof MinResourceCostOp) {
                    continue;
                }

                $spec = $this->resourceSpec($slice, $op->getResourceCode());
                $minimum = $this->nativeValue($op->getMinimum(), $spec);
                $minimums[$op->getResourceCode()] = isset($minimums[$op->getResourceCode()])
                    ? $this->arithmetic->maxValue($minimums[$op->getResourceCode()], $minimum)
                    : $minimum;
            }
        }

        return $minimums;
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

    /**
     * Читает целое поле native object.
     *
     * @param array<string, mixed> $value Object.
     * @param string $key Field.
     *
     * @return int Value.
     *
     * @throws GameInvalidException If field is not integer.
     */
    private function integer(array $value, string $key): int
    {
        if (!is_int($value[$key] ?? null)) {
            throw new GameInvalidException('Game action dimensional amount is invalid');
        }

        return $value[$key];
    }
}
