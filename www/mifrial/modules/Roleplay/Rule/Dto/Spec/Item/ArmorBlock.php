<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Блок брони предмета.
 */
final class ArmorBlock
{
    /**
     * Создаёт блок.
     *
     * @param int|null $strengthPenalty Штраф силы.
     * @param DimensionalNumber|null $maxAgility Потолок ловкости.
     * @param array<int, CharacteristicLimit> $characteristicLimits Лимиты.
     * @param array<int, DefenseSlot> $defenseSlots Слоты защиты.
     * @param array<int, ResistanceSlot> $resistanceSlots Слоты сопротивления.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $strengthPenalty,
        private readonly ?DimensionalNumber $maxAgility,
        private readonly array $characteristicLimits,
        private readonly array $defenseSlots,
        private readonly array $resistanceSlots,
    ) {
    }

    /**
     * Штраф силы.
     *
     * @return int|null Число или null.
     */
    public function getStrengthPenalty(): ?int
    {
        return $this->strengthPenalty;
    }

    /**
     * Потолок ловкости.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getMaxAgility(): ?DimensionalNumber
    {
        return $this->maxAgility;
    }

    /**
     * Лимиты характеристик.
     *
     * @return array<int, CharacteristicLimit> Список.
     */
    public function getCharacteristicLimits(): array
    {
        return $this->characteristicLimits;
    }

    /**
     * Слоты защиты.
     *
     * @return array<int, DefenseSlot> Список.
     */
    public function getDefenseSlots(): array
    {
        return $this->defenseSlots;
    }

    /**
     * Слоты сопротивления.
     *
     * @return array<int, ResistanceSlot> Список.
     */
    public function getResistanceSlots(): array
    {
        return $this->resistanceSlots;
    }
}
