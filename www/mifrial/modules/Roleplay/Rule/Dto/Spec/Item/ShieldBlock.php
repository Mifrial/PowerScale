<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Блок щита предмета.
 */
final class ShieldBlock
{
    /**
     * Создаёт блок.
     *
     * @param DimensionalNumber|null $minStrength Минимальная сила.
     * @param DimensionalNumber|null $durability Прочность.
     * @param array<int, WeaponProfile> $weaponProfiles Профили атаки.
     * @param array<int, CharacteristicLimit> $characteristicLimits Лимиты.
     *
     * @return void
     */
    public function __construct(
        private readonly ?DimensionalNumber $minStrength,
        private readonly ?DimensionalNumber $durability,
        private readonly array $weaponProfiles,
        private readonly array $characteristicLimits,
    ) {
    }

    /**
     * Минимальная сила.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getMinStrength(): ?DimensionalNumber
    {
        return $this->minStrength;
    }

    /**
     * Прочность.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getDurability(): ?DimensionalNumber
    {
        return $this->durability;
    }

    /**
     * Профили атаки.
     *
     * @return array<int, WeaponProfile> Список.
     */
    public function getWeaponProfiles(): array
    {
        return $this->weaponProfiles;
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
}
