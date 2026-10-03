<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;

/**
 * Урон профиля оружия.
 */
final class WeaponDamage
{
    /**
     * Создаёт урон.
     *
     * @param DimensionalFormula $formula Формула.
     * @param string|null $damageTypeCode Тип урона.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalFormula $formula,
        private readonly ?string $damageTypeCode,
    ) {
    }

    /**
     * Формула.
     *
     * @return DimensionalFormula Узел.
     */
    public function getFormula(): DimensionalFormula
    {
        return $this->formula;
    }

    /**
     * Тип урона.
     *
     * @return string|null Код или null.
     */
    public function getDamageTypeCode(): ?string
    {
        return $this->damageTypeCode;
    }
}
