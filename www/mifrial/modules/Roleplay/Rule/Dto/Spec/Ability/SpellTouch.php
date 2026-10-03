<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Доставка заклинания касанием.
 */
final class SpellTouch
{
    /**
     * Создаёт касание.
     *
     * @param bool $weaponDamage Урон оружия.
     * @param int $attackSrBonus Бонус успеха удара.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $weaponDamage,
        private readonly int $attackSrBonus,
    ) {
    }

    /**
     * Урон оружия.
     *
     * @return bool true, если применяется.
     */
    public function isWeaponDamage(): bool
    {
        return $this->weaponDamage;
    }

    /**
     * Бонус успеха.
     *
     * @return int Число.
     */
    public function getAttackSrBonus(): int
    {
        return $this->attackSrBonus;
    }
}
