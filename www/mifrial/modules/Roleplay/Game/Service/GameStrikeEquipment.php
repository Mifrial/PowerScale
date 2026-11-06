<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperations;

/**
 * Снаряжение удара: выбранный предмет, строка инвентаря и руки.
 */
final class GameStrikeEquipment
{
    /**
     * Принимает срезы ревизии.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
    ) {
    }

    /**
     * Сколько рук занимает оружие в этой атаке.
     *
     * Удержание — `occupy_hands` предмета (с учётом выбора строки инвентаря),
     * плюс `extra_hands` профиля действия. По умолчанию действие занимает
     * столько же рук, сколько удержание.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $profileIndex Смещение профиля.
     *
     * @return int Число рук.
     *
     * @throws GameInvalidException Если предмет, строка или профиль битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function occupyHands(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $itemInventoryId,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): int {
        $hands = $this->slices->item($spaceId, $rulesRevision, $itemRuleCode)->getOccupyHands();
        if ($hands === null) {
            return 0;
        }

        $row = $this->inventoryRow($choices, $itemInventoryId, $itemRuleCode);
        $value = $row['occupyHands'] ?? $hands->getMin();
        $value = is_int($value) ? $value : $hands->getMin();
        $rest = max($hands->getMin(), min($hands->getMax(), $value));

        $extra = $this->slices->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
            ->getExtraHands();

        return $rest + (is_int($extra) && $extra > 0 ? $extra : 0);
    }

    /**
     * Бонус к Силе удара за удержание оружия.
     *
     * 2–3 руки дают +2, 4 и больше — +3; одна рука без бонуса.
     * Совпадает с фронтом (`CharacterHandsService::gripBonus`).
     *
     * @param int $occupyHands Занятые руки действия.
     *
     * @return int Бонус.
     */
    public function gripBonus(int $occupyHands): int
    {
        if ($occupyHands >= 4) {
            return 3;
        }

        return $occupyHands >= 2 ? 2 : 0;
    }

    /**
     * Выбранный живой предмет строки инвентаря.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Строка инвентаря.
     * @param string $itemRuleCode Ожидаемый код.
     *
     * @return ItemSpec Живой предмет.
     *
     * @throws GameInvalidException Если instance невалиден.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    public function selectedItem(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $inventoryId,
        string $itemRuleCode,
    ): ItemSpec {
        $this->assertInventoryItem($choices, $inventoryId, $itemRuleCode, $spaceId, $rulesRevision);

        return $this->effectiveItem($spaceId, $rulesRevision, $this->row($choices, $inventoryId));
    }

    /**
     * Разрешает выбранный предмет без доверия к его rule code.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Строка инвентаря.
     *
     * @return ItemSpec Живой предмет.
     *
     * @throws GameInvalidException Если instance невалиден.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    public function selectedItemById(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $inventoryId,
    ): ItemSpec {
        $row = $this->row($choices, $inventoryId, 'Game strike block item is invalid');
        if (($row['equipped'] ?? false) !== true || !is_string($row['ruleCode'] ?? null)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        return $this->effectiveItem($spaceId, $rulesRevision, $row);
    }

    /**
     * Проверяет выбранную надетую строку инвентаря.
     *
     * @param array<string, mixed> $choices Выборы листа.
     * @param int $inventoryId Идентификатор строки.
     * @param string $itemRuleCode Ожидаемый код предмета.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param int $instanceIndex Индекс экземпляра.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка отсутствует или не совпадает.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertInventoryItem(
        array $choices,
        int $inventoryId,
        string $itemRuleCode,
        int $spaceId,
        int $rulesRevision,
        int $instanceIndex = 0,
    ): void {
        $row = $this->row($choices, $inventoryId);
        $item = $this->slices->item($spaceId, $rulesRevision, $itemRuleCode);
        if ($this->rowMatches($row, $item, $itemRuleCode) && $this->quantityAllows($row, $instanceIndex)) {
            return;
        }

        throw new GameInvalidException('Game strike inventory item is invalid');
    }

    /**
     * Строка в руках: надета или врождённое оружие.
     *
     * @param array<string, mixed> $row Строка инвентаря.
     * @param ItemSpec $item Предмет.
     *
     * @return bool true, если доступна в бою.
     */
    public function isCarried(array $row, ItemSpec $item): bool
    {
        return ($row['equipped'] ?? false) === true || $item->isInnate();
    }

    /**
     * Одна строка инвентаря по id.
     *
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Идентификатор строки.
     * @param string $message Текст отказа.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если строки нет или их несколько.
     */
    public function row(
        array $choices,
        int $inventoryId,
        string $message = 'Game strike inventory item is invalid',
    ): array {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game strike inventory is invalid');
        }

        $matches = [];
        foreach ($inventory as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matches[] = $row;
            }
        }

        if (count($matches) !== 1) {
            throw new GameInvalidException($message);
        }

        return $matches[0];
    }

    /**
     * Строка совпадает с предметом и надета.
     *
     * @param array<string, mixed> $row Строка.
     * @param ItemSpec $item Предмет.
     * @param string $itemRuleCode Код.
     *
     * @return bool true, если совпадает.
     */
    private function rowMatches(array $row, ItemSpec $item, string $itemRuleCode): bool
    {
        return $this->isCarried($row, $item) && ($row['ruleCode'] ?? null) === $itemRuleCode;
    }

    /**
     * Остатка хватает на выбранный экземпляр.
     *
     * @param array<string, mixed> $row Строка.
     * @param int $instanceIndex Индекс экземпляра.
     *
     * @return bool true, если хватает.
     */
    private function quantityAllows(array $row, int $instanceIndex): bool
    {
        $quantity = $row['quantity'] ?? 1;
        if (!is_int($quantity)) {
            return false;
        }

        return $instanceIndex >= 0 && $instanceIndex < $quantity;
    }

    /**
     * Применяет live operations выбранной inventory instance к live item.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $row Строка инвентаря.
     *
     * @return ItemSpec Effective spec.
     *
     * @throws GameInvalidException Если item или modifier битые.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    private function effectiveItem(int $spaceId, int $rulesRevision, array $row): ItemSpec
    {
        $code = $row['ruleCode'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game strike inventory item is invalid');
        }

        $slice = $this->slices->slice($spaceId, $rulesRevision);
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof ItemSpec) {
            throw new GameInvalidException('Game strike item is invalid');
        }

        return ItemModifierOperations::apply(
            $spec,
            $this->modifierSpecs($slice, $row['modifiers'] ?? null),
            $rule->getKeywordCodes(),
        );
    }

    /**
     * Разрешает live modifier specs строки инвентаря.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param mixed $codes Коды модификаторов.
     *
     * @return array<int, ItemModifierSpec> Specs.
     *
     * @throws GameInvalidException Если коды или spec битые.
     */
    private function modifierSpecs(CharacterRuleSlice $slice, mixed $codes): array
    {
        if ($codes === null || $codes === []) {
            return [];
        }

        if (!is_array($codes)) {
            throw new GameInvalidException('Game strike item modifiers are invalid');
        }

        $specs = [];
        foreach ($codes as $code) {
            $specs[] = $this->modifierSpec($slice, $code);
        }

        return $specs;
    }

    /**
     * Один live modifier spec по коду.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param mixed $code Код модификатора.
     *
     * @return ItemModifierSpec Spec.
     *
     * @throws GameInvalidException Если код или spec битые.
     */
    private function modifierSpec(CharacterRuleSlice $slice, mixed $code): ItemModifierSpec
    {
        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game strike item modifier is invalid');
        }

        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof ItemModifierSpec) {
            throw new GameInvalidException('Game strike item modifier is invalid');
        }

        return $spec;
    }

    /**
     * Строка инвентаря выбранного предмета.
     *
     * @param array<string, mixed> $choices Authoritative choices.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Предмет.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если строки нет.
     */
    private function inventoryRow(array $choices, int $itemInventoryId, string $itemRuleCode): array
    {
        $row = $this->row($choices, $itemInventoryId);
        if (($row['ruleCode'] ?? null) !== $itemRuleCode) {
            throw new GameInvalidException('Game strike inventory item is invalid');
        }

        return $row;
    }
}
