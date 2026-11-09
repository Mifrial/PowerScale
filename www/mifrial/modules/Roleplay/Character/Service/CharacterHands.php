<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithComponents;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\SomaticComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemHands;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;

/**
 * Руки персонажа: занятость надетого, свободные руки и требование действия.
 *
 * Источник истины — строки инвентаря (`equipped`, `occupyHands`), а не
 * отдельный счётчик. Внешняя занятость (зажатые раны и подобное) приходит
 * явным `extraOccupied`.
 */
final class CharacterHands
{
    private const int TOTAL_HANDS = 2;

    /**
     * Создаёт расчёт рук.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $choices Документ choices с инвентарём.
     * @param int $extraOccupied Внешние занятые руки.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRuleSlice $slice,
        private readonly array $choices,
        private readonly int $extraOccupied = 0,
    ) {
    }

    /**
     * Всего рук у персонажа.
     *
     * @return int Число.
     */
    public function getTotalHands(): int
    {
        return self::TOTAL_HANDS;
    }

    /**
     * Сколько рук занимает предмет.
     *
     * @param string $ruleCode Код предмета.
     * @param int|null $rowValue Занятость выбранной строки.
     *
     * @return int Число.
     */
    public function getItemOccupancy(string $ruleCode, ?int $rowValue = null): int
    {
        $spec = $this->slice->findLive($ruleCode)?->getSpec();

        return $spec instanceof ItemSpec ? $this->getItemOccupancyOf($spec, $rowValue) : 0;
    }

    /**
     * Сколько рук занимает уже найденный предмет.
     *
     * @param ItemSpec $item Живой предмет.
     * @param int|null $rowValue Занятость выбранной строки.
     *
     * @return int Число.
     */
    public function getItemOccupancyOf(ItemSpec $item, ?int $rowValue = null): int
    {
        if ($item->isInnate()) {
            return 0;
        }

        $hands = $item->getOccupyHands();
        if ($hands instanceof ItemHands) {
            return $this->clamp($rowValue ?? $hands->getMin(), $hands->getMin(), $hands->getMax());
        }

        return $item->getWeapon() === null && $item->getShield() === null ? 0 : 1;
    }

    /**
     * Сколько рук занято надетым и внешними держателями.
     *
     * @return int Число.
     */
    public function getOccupiedHands(): int
    {
        return $this->equippedHands(null) + max(0, $this->extraOccupied);
    }

    /**
     * Сколько рук свободно.
     *
     * @return int Число.
     */
    public function getFreeHands(): int
    {
        return max(0, $this->getTotalHands() - $this->getOccupiedHands());
    }

    /**
     * Сколько свободных рук требует действие.
     *
     * @param AbilityWithComponents $spec Карточка действия или заклинания.
     *
     * @return int Число.
     */
    public static function getRequiredHandsOf(AbilityWithComponents $spec): int
    {
        $required = 0;
        foreach ($spec->getComponents() as $component) {
            if ($component instanceof SomaticComponent) {
                $required += max(0, $component->getOccupyHands() ?? 0);
            }
        }

        return $required;
    }

    /**
     * Помещается ли предмет в свободные руки.
     *
     * @param string $ruleCode Код предмета.
     * @param int|null $rowValue Занятость выбранной строки.
     * @param int|null $exceptInventoryId Строка, которую не учитываем.
     *
     * @return bool true, если помещается.
     */
    public function canEquip(string $ruleCode, ?int $rowValue = null, ?int $exceptInventoryId = null): bool
    {
        $occupied = $this->equippedHands($exceptInventoryId) + max(0, $this->extraOccupied);

        return $this->getItemOccupancy($ruleCode, $rowValue) <= $this->getTotalHands() - $occupied;
    }

    /**
     * Сумма занятости надетых строк.
     *
     * @param int|null $exceptInventoryId Строка, которую не учитываем.
     *
     * @return int Число.
     */
    private function equippedHands(?int $exceptInventoryId): int
    {
        $inventory = $this->choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            return 0;
        }

        $occupied = 0;
        foreach ($inventory as $row) {
            if (!$this->isCountedRow($row, $exceptInventoryId)) {
                continue;
            }

            $ruleCode = $row['ruleCode'];
            $rowValue = $row['occupyHands'] ?? null;
            $occupied += $this->getItemOccupancy($ruleCode, is_int($rowValue) ? $rowValue : null);
        }

        return $occupied;
    }

    /**
     * Строка входит в занятость.
     *
     * @param mixed $row Строка инвентаря.
     * @param int|null $exceptInventoryId Строка, которую не учитываем.
     *
     * @return bool true, если строка учитывается.
     */
    private function isCountedRow(mixed $row, ?int $exceptInventoryId): bool
    {
        if (!is_array($row) || ($row['equipped'] ?? false) !== true || !is_string($row['ruleCode'] ?? null)) {
            return false;
        }

        $id = $row['id'] ?? null;

        return $exceptInventoryId === null || !is_int($id) || $id !== $exceptInventoryId;
    }

    /**
     * Зажимает занятость строки в диапазон предмета.
     *
     * @param int $value Значение.
     * @param int $min Минимум.
     * @param int $max Максимум.
     *
     * @return int Число.
     */
    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
