<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperations;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Слои надетых предметов: броня каждой строки и профиль выбранного блока.
 */
final class CharacterCombatItemLayers
{
    private const BLOCK_SOURCE = 'От блокирования';

    /**
     * Слои брони всех надетых строк с предметом.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param array<int, mixed> $inventory Строки.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     *
     * @throws CharacterInvalidException Если код или spec битый.
     */
    public function armor(CharacterRuleSlice $slice, array $inventory): array
    {
        $layers = [];
        foreach ($inventory as $row) {
            $item = $this->equippedItem($slice, $row);
            if ($item === null) {
                continue;
            }

            array_push($layers, ...$this->armorOf($item));
        }

        return $layers;
    }

    /**
     * Слои профиля блока выбранной надетой строки.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param array<int, mixed> $inventory Строки.
     * @param int $inventoryId Id строки.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     *
     * @throws CharacterInvalidException Если строки, надетости или профиля нет.
     */
    public function block(CharacterRuleSlice $slice, array $inventory, int $inventoryId): array
    {
        $item = $this->equippedItem($slice, $this->rowById($inventory, $inventoryId));
        $profile = $item?->getBlockProfile();
        if ($item === null || $profile === null) {
            $this->reject();
        }

        $layers = [$this->defense($profile->getDefense(), null, self::BLOCK_SOURCE, true)];
        foreach ($profile->getResistances() as $slot) {
            $layers[] = $this->resistance(
                $slot->getValue(),
                $slot->getDurability(),
                $slot->getSourceCode(),
                $slot->getDamageTypeCode(),
                true,
            );
        }

        return $layers;
    }

    /**
     * Надетый предмет после модификаторов. Custom и снятое — null.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param mixed $row Строка.
     *
     * @return ItemSpec|null Предмет или null.
     *
     * @throws CharacterInvalidException Если код заявлен и не находится.
     */
    private function equippedItem(CharacterRuleSlice $slice, mixed $row): ?ItemSpec
    {
        if (!is_array($row) || ($row['equipped'] ?? false) !== true) {
            return null;
        }

        $code = $row['ruleCode'] ?? null;
        if (!is_string($code) || $code === '') {
            return null;
        }

        return $this->changed($slice, $code, $row['modifiers'] ?? null);
    }

    /**
     * Предмет после apply.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param string $code Код предмета.
     * @param mixed $modifiers Коды модификаторов.
     *
     * @return ItemSpec Spec.
     *
     * @throws CharacterInvalidException Если правило битое.
     */
    private function changed(CharacterRuleSlice $slice, string $code, mixed $modifiers): ItemSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ItemSpec) {
            $this->reject();
        }

        return ItemModifierOperations::apply($spec, $this->modifiers($slice, $modifiers), $rule->getKeywordCodes());
    }

    /**
     * Живые spec модификаторов.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param mixed $codes Коды или отсутствие ключа.
     *
     * @return array<int, ItemModifierSpec> Spec.
     *
     * @throws CharacterInvalidException Если код не найден.
     */
    private function modifiers(CharacterRuleSlice $slice, mixed $codes): array
    {
        if ($codes === null || $codes === []) {
            return [];
        }

        if (!is_array($codes)) {
            $this->reject();
        }

        $specs = [];
        foreach ($codes as $code) {
            $specs[] = $this->modifier($slice, $code);
        }

        return $specs;
    }

    /**
     * Один модификатор.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param mixed $code Код.
     *
     * @return ItemModifierSpec Spec.
     *
     * @throws CharacterInvalidException Если кода нет.
     */
    private function modifier(CharacterRuleSlice $slice, mixed $code): ItemModifierSpec
    {
        if (!is_string($code) || $code === '') {
            $this->reject();
        }

        $rule = $slice->findLive($code);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ItemModifierSpec) {
            $this->reject();
        }

        return $spec;
    }

    /**
     * Строка с id.
     *
     * @param array<int, mixed> $inventory Строки.
     * @param int $inventoryId Id.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws CharacterInvalidException Если строки нет.
     */
    private function rowById(array $inventory, int $inventoryId): array
    {
        foreach ($inventory as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                return $row;
            }
        }

        $this->reject();
    }

    /**
     * Слоты брони предмета.
     *
     * @param ItemSpec $item Предмет.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     */
    private function armorOf(ItemSpec $item): array
    {
        $armor = $item->getArmor();
        if ($armor === null) {
            return [];
        }

        $layers = [];
        foreach ($armor->getDefenseSlots() as $slot) {
            $layers[] = $this->defense($slot->getDefense(), $slot->getDurability(), $slot->getSourceCode(), false);
        }

        foreach ($armor->getResistanceSlots() as $slot) {
            $layers[] = $this->resistance(
                $slot->getValue(),
                $slot->getDurability(),
                $slot->getSourceCode(),
                $slot->getDamageTypeCode(),
                false,
            );
        }

        return $layers;
    }

    /**
     * Слой защиты.
     *
     * @param DimensionalNumber $value Пара.
     * @param int|null $durability Порог.
     * @param string|null $sourceCode Источник слота.
     * @param bool $fromBlock Слой блока.
     *
     * @return CharacterCombatLayer Слой.
     */
    private function defense(
        DimensionalNumber $value,
        ?int $durability,
        ?string $sourceCode,
        bool $fromBlock,
    ): CharacterCombatLayer {
        return new CharacterCombatLayer('defense', $value, $durability, $this->source($sourceCode, $fromBlock), null);
    }

    /**
     * Слой сопротивления.
     *
     * @param DimensionalNumber $value Пара.
     * @param int|null $durability Порог.
     * @param string|null $sourceCode Источник слота.
     * @param string|null $damageTypeCode Тип урона.
     * @param bool $fromBlock Слой блока.
     *
     * @return CharacterCombatLayer Слой.
     */
    private function resistance(
        DimensionalNumber $value,
        ?int $durability,
        ?string $sourceCode,
        ?string $damageTypeCode,
        bool $fromBlock,
    ): CharacterCombatLayer {
        return new CharacterCombatLayer(
            'resistance',
            $value,
            $durability,
            $this->source($sourceCode, $fromBlock),
            $damageTypeCode,
        );
    }

    /**
     * Пустой source блока становится «От блокирования».
     *
     * @param string|null $sourceCode Источник слота.
     * @param bool $fromBlock Слой блока.
     *
     * @return string Код.
     */
    private function source(?string $sourceCode, bool $fromBlock): string
    {
        if ($sourceCode !== null && $sourceCode !== '') {
            return $sourceCode;
        }

        return $fromBlock ? self::BLOCK_SOURCE : '';
    }

    /**
     * Отказ проекции.
     *
     * @return never
     *
     * @throws CharacterInvalidException Всегда.
     */
    private function reject(): never
    {
        throw new CharacterInvalidException('Character combat layers are invalid');
    }
}
