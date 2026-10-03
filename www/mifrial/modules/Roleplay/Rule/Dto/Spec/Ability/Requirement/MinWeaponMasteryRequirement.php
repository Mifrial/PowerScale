<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Минимум мастерства оружия.
 */
final class MinWeaponMasteryRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $keywordCode Признак оружия.
     * @param int $minLevel Минимум уровня.
     *
     * @return void
     */
    public function __construct(
        private readonly string $keywordCode,
        private readonly int $minLevel,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'min_weapon_mastery';
    }

    /**
     * Признак.
     *
     * @return string Код.
     */
    public function getKeywordCode(): string
    {
        return $this->keywordCode;
    }

    /**
     * Минимум уровня.
     *
     * @return int Уровень.
     */
    public function getMinLevel(): int
    {
        return $this->minLevel;
    }
}
