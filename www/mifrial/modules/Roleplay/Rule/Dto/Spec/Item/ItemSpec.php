<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

/**
 * Spec предмета: цена, врождённость, броня и щит.
 */
final class ItemSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param string $category Категория.
     * @param int|null $costGm Цена в GM.
     * @param bool $innate Врождённость.
     * @param \Mifrial\Roleplay\Rule\Value\DimensionalNumber|null $weight Вес.
     * @param array<int, string> $specialRuleCodes Особые правила.
     * @param string|null $groupCode Группа.
     * @param string|null $proficiencyFamilyCode Семья владения.
     * @param int|null $magicConductor Проводник магии.
     * @param array<int, AdvantageModifier> $advantages Помехи предмета.
     * @param array<int, ItemCheckAdvantage> $checkAdvantages Помехи проверок.
     * @param ItemHands|null $occupyHands Слоты рук.
     * @param WeaponBlock|null $weapon Оружие.
     * @param ArmorBlock|null $armor Броня.
     * @param ShieldBlock|null $shield Щит.
     *
     * @return void
     */
    public function __construct(
        private readonly string $category,
        private readonly ?int $costGm,
        private readonly bool $innate,
        private readonly ?\Mifrial\Roleplay\Rule\Value\DimensionalNumber $weight,
        private readonly array $specialRuleCodes,
        private readonly ?string $groupCode,
        private readonly ?string $proficiencyFamilyCode,
        private readonly ?int $magicConductor,
        private readonly array $advantages,
        private readonly array $checkAdvantages,
        private readonly ?ItemHands $occupyHands,
        private readonly ?WeaponBlock $weapon,
        private readonly ?ArmorBlock $armor,
        private readonly ?ShieldBlock $shield,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'item';
    }

    /**
     * Категория.
     *
     * @return string Код.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Цена в GM.
     *
     * @return int|null Число или null.
     */
    public function getCostGm(): ?int
    {
        return $this->costGm;
    }

    /**
     * Предмет врождённый.
     *
     * @return bool true, если innate.
     */
    public function isInnate(): bool
    {
        return $this->innate;
    }

    /**
     * Вес.
     *
     * @return \Mifrial\Roleplay\Rule\Value\DimensionalNumber|null Число или null.
     */
    public function getWeight(): ?\Mifrial\Roleplay\Rule\Value\DimensionalNumber
    {
        return $this->weight;
    }

    /**
     * Особые правила.
     *
     * @return array<int, string> Коды.
     */
    public function getSpecialRuleCodes(): array
    {
        return $this->specialRuleCodes;
    }

    /**
     * Группа.
     *
     * @return string|null Код или null.
     */
    public function getGroupCode(): ?string
    {
        return $this->groupCode;
    }

    /**
     * Семья владения.
     *
     * @return string|null Код или null.
     */
    public function getProficiencyFamilyCode(): ?string
    {
        return $this->proficiencyFamilyCode;
    }

    /**
     * Проводник магии.
     *
     * @return int|null Число или null.
     */
    public function getMagicConductor(): ?int
    {
        return $this->magicConductor;
    }

    /**
     * Помехи предмета.
     *
     * @return array<int, AdvantageModifier> Список.
     */
    public function getAdvantages(): array
    {
        return $this->advantages;
    }

    /**
     * Помехи проверок.
     *
     * @return array<int, ItemCheckAdvantage> Список.
     */
    public function getCheckAdvantages(): array
    {
        return $this->checkAdvantages;
    }

    /**
     * Слоты рук.
     *
     * @return ItemHands|null Слоты или null.
     */
    public function getOccupyHands(): ?ItemHands
    {
        return $this->occupyHands;
    }

    /**
     * Оружие.
     *
     * @return WeaponBlock|null Блок или null.
     */
    public function getWeapon(): ?WeaponBlock
    {
        return $this->weapon;
    }

    /**
     * Блок брони.
     *
     * @return ArmorBlock|null Блок или null.
     */
    public function getArmor(): ?ArmorBlock
    {
        return $this->armor;
    }

    /**
     * Блок щита.
     *
     * @return ShieldBlock|null Блок или null.
     */
    public function getShield(): ?ShieldBlock
    {
        return $this->shield;
    }
}
