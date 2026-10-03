<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Минимум значения характеристики.
 */
final class CharacteristicValueRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $characteristicCode Характеристика.
     * @param DimensionalNumber $min Минимум.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
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
        return 'characteristic_value';
    }

    /**
     * Характеристика.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Минимум.
     *
     * @return DimensionalNumber Число.
     */
    public function getMin(): DimensionalNumber
    {
        return $this->min;
    }
}
