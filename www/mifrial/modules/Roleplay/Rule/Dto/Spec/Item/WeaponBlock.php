<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Блок оружия предмета.
 */
final class WeaponBlock
{
    /**
     * Создаёт блок.
     *
     * @param DimensionalNumber|null $minStrength Минимальная сила.
     * @param array<int, WeaponProfile> $weaponProfiles Профили атаки.
     * @param DimensionalNumber|null $durability Прочность.
     * @param int|null $minActionCost Минимум ОД.
     *
     * @return void
     */
    public function __construct(
        private readonly ?DimensionalNumber $minStrength,
        private readonly array $weaponProfiles,
        private readonly ?DimensionalNumber $durability,
        private readonly ?int $minActionCost,
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
     * Профили атаки.
     *
     * @return array<int, WeaponProfile> Список.
     */
    public function getWeaponProfiles(): array
    {
        return $this->weaponProfiles;
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
     * Минимум ОД.
     *
     * @return int|null Число или null.
     */
    public function getMinActionCost(): ?int
    {
        return $this->minActionCost;
    }
}
