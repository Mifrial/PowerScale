<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Штраф к эффективности уклонения от дальней атаки указанного профиля.
 */
final class DodgeRangedPenalty
{
    /**
     * Создаёт штраф.
     *
     * @param string $profileType Тип профиля атаки, к которому применяется штраф.
     * @param DimensionalNumber $distance Порог дистанции включительно.
     * @param int $delta Дельта эффективности по шкале характеристик.
     *
     * @return void
     */
    public function __construct(
        private readonly string $profileType,
        private readonly DimensionalNumber $distance,
        private readonly int $delta,
    ) {
    }

    /**
     * Тип профиля атаки.
     *
     * @return string Код профиля.
     */
    public function getProfileType(): string
    {
        return $this->profileType;
    }

    /**
     * Порог дистанции.
     *
     * @return DimensionalNumber Дистанция.
     */
    public function getDistance(): DimensionalNumber
    {
        return $this->distance;
    }

    /**
     * Дельта эффективности.
     *
     * @return int Пункты по шкале характеристик.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }
}
