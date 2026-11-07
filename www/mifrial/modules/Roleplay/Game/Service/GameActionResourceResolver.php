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
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разрешает live action resources до вызова mutation boundary.
 */
final class GameActionResourceResolver
{
    private readonly CharacterResourceStorage $storage;

    private readonly CharacterResourceArithmetic $arithmetic;

    private readonly GameActionChosenRange $range;

    private readonly GameActionResourceComponents $components;

    private readonly GameActionResourceMinimums $minimums;

    /**
     * Создаёт resolver.
     *
     * @return void
     */
    public function __construct()
    {
        $this->storage = new CharacterResourceStorage();
        $this->arithmetic = new CharacterResourceArithmetic();
        $this->range = new GameActionChosenRange();
        $this->components = new GameActionResourceComponents();
        $this->minimums = new GameActionResourceMinimums($this->arithmetic);
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
     * @param bool $allowZeroChosen Разрешить chosen 0.
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
        bool $allowZeroChosen = false,
    ): array {
        $action = $slice->findLive($actionRuleCode);
        if ($action === null || $action->isSpecBroken()) {
            throw new GameInvalidException('Game action rule is invalid');
        }

        $spec = $action->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return [];
        }

        $components = $this->components->of($spec);
        $this->components->assertChosenAmounts($chosenAmounts, $components);
        if ($components === []) {
            return [];
        }

        $minimums = $this->minimumsFor($slice, $itemInventoryId, $itemRuleCode, $choices, $components);

        return $this->spends($slice, $components, $chosenAmounts, $sheet, $allowZeroChosen, $minimums);
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
        $rows = $this->rows($sheet);
        try {
            $typed = $this->storage->parseRows($rows, $slice);

            return $this->storage->canSpendRows($typed, $slice, $spends);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resources are invalid', $exception);
        }
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
        $codes = $this->reactionCodes($slice, $reaction);
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
     * Минимумы выбранного предмета или пустая карта.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Код предмета.
     * @param array<string, mixed> $choices Выбор.
     * @param array<int, ResourceComponent> $components Компоненты.
     *
     * @return array<string, ResourceValue> Минимумы.
     *
     * @throws GameInvalidException Если предмет или модификатор битый.
     */
    private function minimumsFor(
        CharacterRuleSlice $slice,
        int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $components,
    ): array {
        if ($itemRuleCode === '' || $itemInventoryId < 1) {
            return [];
        }

        return $this->minimums->of($slice, $itemInventoryId, $itemRuleCode, $choices, $components);
    }

    /**
     * Траты по компонентам.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, ResourceComponent> $components Компоненты.
     * @param array<string, mixed> $chosenAmounts Выбранные суммы.
     * @param array<string, mixed> $sheet Лист.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     * @param array<string, ResourceValue> $minimums Минимумы.
     *
     * @return array<int, ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если сумма чужая.
     */
    private function spends(
        CharacterRuleSlice $slice,
        array $components,
        array $chosenAmounts,
        array $sheet,
        bool $allowZeroChosen,
        array $minimums,
    ): array {
        $spends = [];
        foreach ($components as $component) {
            $spends[] = $this->spend($slice, $component, $chosenAmounts, $sheet, $allowZeroChosen, $minimums);
        }

        return $spends;
    }

    /**
     * Коды карточек реакции с нужным combat_action.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $reaction Реакция.
     *
     * @return array<int, string> Коды.
     */
    private function reactionCodes(CharacterRuleSlice $slice, string $reaction): array
    {
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

        return $codes;
    }

    /**
     * Трата одного ресурсного компонента.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param ResourceComponent $component Компонент.
     * @param array<string, mixed> $chosenAmounts Выбранные суммы.
     * @param array<string, mixed> $sheet Лист.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     * @param array<string, ResourceValue> $minimums Минимумы.
     *
     * @return ResourceSpend Трата.
     *
     * @throws GameInvalidException Если сумма чужая.
     */
    private function spend(
        CharacterRuleSlice $slice,
        ResourceComponent $component,
        array $chosenAmounts,
        array $sheet,
        bool $allowZeroChosen,
        array $minimums,
    ): ResourceSpend {
        $resourceSpec = $this->resourceSpec($slice, $component->getResourceCode());
        $amount = $this->amount(
            $component,
            $chosenAmounts,
            $resourceSpec,
            $this->currentScalar($sheet, $component->getResourceCode(), $slice, $component),
            $allowZeroChosen,
        );
        $minimum = $minimums[$component->getResourceCode()] ?? null;
        if ($minimum instanceof ResourceValue) {
            $amount = $this->arithmetic->maxValue($amount, $minimum);
        }

        return new ResourceSpend($component->getResourceCode(), $amount);
    }

    /**
     * Возвращает native value из компонента.
     *
     * @param ResourceComponent $component Компонент.
     * @param array<string, mixed> $chosenAmounts Server choices.
     * @param ResourceSpec $spec Live resource spec.
     * @param ScalarResourceValue|null $current Current scalar value.
     * @param bool $allowZeroChosen Разрешить chosen 0.
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
        bool $allowZeroChosen,
    ): ResourceValue {
        $amount = $component->getAmount();
        if ($amount instanceof ChosenAmount) {
            $amount = $chosenAmounts[$component->getResourceCode()] ?? null;
        }

        try {
            return $this->typedAmount($component, $spec, $amount, $current, $allowZeroChosen);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resource amount is invalid', $exception);
        }
    }

    /**
     * Типизированная сумма по форме ресурса.
     *
     * @param ResourceComponent $component Компонент.
     * @param ResourceSpec $spec Ресурс.
     * @param mixed $amount Сумма.
     * @param ScalarResourceValue|null $current Текущее значение.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     *
     * @return ResourceValue Сумма.
     *
     * @throws GameInvalidException Если форма или диапазон чужие.
     * @throws CharacterInvalidException Если значение ресурса отрицательное.
     */
    private function typedAmount(
        ResourceComponent $component,
        ResourceSpec $spec,
        mixed $amount,
        ?ScalarResourceValue $current,
        bool $allowZeroChosen,
    ): ResourceValue {
        if ($spec->isDimensional() && is_array($amount) && !array_is_list($amount)) {
            return new DimensionalResourceValue(new DimensionalNumber(
                $this->integer($amount, 'base'),
                $this->integer($amount, 'size'),
            ));
        }

        if (!$spec->isDimensional() && is_int($amount)) {
            $this->assertRange($component, $amount, $current, $allowZeroChosen);

            return new ScalarResourceValue($amount);
        }

        throw new GameInvalidException('Game action resource shape is invalid');
    }

    /**
     * Проверяет диапазон выбранной суммы.
     *
     * @param ResourceComponent $component Компонент.
     * @param int $amount Сумма.
     * @param ScalarResourceValue|null $current Текущее значение.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     *
     * @return void
     *
     * @throws GameInvalidException Если сумма вне диапазона.
     */
    private function assertRange(
        ResourceComponent $component,
        int $amount,
        ?ScalarResourceValue $current,
        bool $allowZeroChosen,
    ): void {
        if (
            $component->getAmount() instanceof ChosenAmount
            && $this->range->isOutOfRange($amount, $current, $allowZeroChosen)
        ) {
            throw new GameInvalidException('Game chosen resource amount is out of range');
        }
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

        $rows = $this->rows($sheet);
        try {
            return $this->findScalar($rows, $slice, $resourceCode);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resources are invalid', $exception);
        }
    }

    /**
     * Строки ресурсов листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<int|string, mixed> Строки.
     *
     * @throws GameInvalidException Если ресурсы не заполнены.
     */
    private function rows(array $sheet): array
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new GameInvalidException('Game action resources require backfill');
        }

        return $rows;
    }

    /**
     * Scalar текущего значения ресурса.
     *
     * @param array<int|string, mixed> $rows Строки ресурсов.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $resourceCode Код ресурса.
     *
     * @return ScalarResourceValue|null Значение или null.
     *
     * @throws CharacterInvalidException Если строки битые.
     */
    private function findScalar(array $rows, CharacterRuleSlice $slice, string $resourceCode): ?ScalarResourceValue
    {
        foreach ($this->storage->parseRows($rows, $slice) as $row) {
            if ($row->getRuleCode() !== $resourceCode) {
                continue;
            }

            $current = $row->getCurrent();

            return $current instanceof ScalarResourceValue ? $current : null;
        }

        return null;
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
