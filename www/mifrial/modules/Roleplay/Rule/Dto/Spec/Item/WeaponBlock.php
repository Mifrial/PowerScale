<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Блок оружия предмета.
 */
final class WeaponBlock
{
    /**
     * Создаёт блок.
     *
     * @param CharacteristicNumber|null $minStrength Минимальная сила.
     * @param array<int, WeaponProfile> $weaponProfiles Профили атаки.
     * @param DimensionalNumber|null $durability Прочность.
     * @param int|null $minActionCost Минимум ОД.
     * @param array<int, array<int, string>> $ammunitionRequirements Наборы кодов ключевых слов боеприпасов.
     *
     * @return void
     */
    public function __construct(
        private readonly ?CharacteristicNumber $minStrength,
        private readonly array $weaponProfiles,
        private readonly ?DimensionalNumber $durability,
        private readonly ?int $minActionCost,
        private readonly array $ammunitionRequirements = [],
    ) {
    }

    /**
     * Минимальная сила.
     *
     * @return CharacteristicNumber|null Число или null.
     */
    public function getMinStrength(): ?CharacteristicNumber
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

    /**
     * Наборы кодов ключевых слов боеприпасов: внешний OR, внутри AND.
     *
     * @return array<int, array<int, string>> Наборы.
     */
    public function getAmmunitionRequirements(): array
    {
        return $this->ammunitionRequirements;
    }
}
