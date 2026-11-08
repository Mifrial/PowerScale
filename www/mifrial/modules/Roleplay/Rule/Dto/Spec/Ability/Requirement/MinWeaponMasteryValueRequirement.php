<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Минимум мастерства боя с оружием.
 *
 * Мастерство — значение профиля оружия (характеристика плюс уровень владения),
 * а не уровень владения семьёй. Для уровня владения есть MinWeaponMasteryRequirement.
 */
final class MinWeaponMasteryValueRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $keywordCode Признак оружия.
     * @param DimensionalNumber $min Минимум мастерства.
     *
     * @return void
     */
    public function __construct(
        private readonly string $keywordCode,
        private readonly DimensionalNumber $min,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'min_weapon_mastery_value';
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
     * Минимум мастерства.
     *
     * @return DimensionalNumber Значение.
     */
    public function getMin(): DimensionalNumber
    {
        return $this->min;
    }
}
