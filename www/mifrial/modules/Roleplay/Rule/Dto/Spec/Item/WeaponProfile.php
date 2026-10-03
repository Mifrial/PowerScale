<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Профиль удара, броска или выстрела.
 */
final class WeaponProfile
{
    /**
     * Создаёт профиль.
     *
     * @param string $type strike, throw или shoot.
     * @param DimensionalFormula $distance Дистанция.
     * @param DimensionalFormula|null $range Дальность.
     * @param WeaponDamage $damage Урон.
     * @param DimensionalFormula $penetration Пробитие.
     * @param DimensionalNumber $accuracy Точность.
     * @param array<int, ActionCharacteristicBase> $actionCharacteristics Базы действия.
     * @param DimensionalNumber|null $falloff Падение силы.
     * @param int|null $dodgeBenefit Польза уклонения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $type,
        private readonly DimensionalFormula $distance,
        private readonly ?DimensionalFormula $range,
        private readonly WeaponDamage $damage,
        private readonly DimensionalFormula $penetration,
        private readonly DimensionalNumber $accuracy,
        private readonly array $actionCharacteristics,
        private readonly ?DimensionalNumber $falloff,
        private readonly ?int $dodgeBenefit,
    ) {
    }

    /**
     * Вид профиля.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Дистанция.
     *
     * @return DimensionalFormula Узел.
     */
    public function getDistance(): DimensionalFormula
    {
        return $this->distance;
    }

    /**
     * Дальность.
     *
     * @return DimensionalFormula|null Узел или null.
     */
    public function getRange(): ?DimensionalFormula
    {
        return $this->range;
    }

    /**
     * Урон.
     *
     * @return WeaponDamage Урон.
     */
    public function getDamage(): WeaponDamage
    {
        return $this->damage;
    }

    /**
     * Пробитие.
     *
     * @return DimensionalFormula Узел.
     */
    public function getPenetration(): DimensionalFormula
    {
        return $this->penetration;
    }

    /**
     * Точность.
     *
     * @return DimensionalNumber Число.
     */
    public function getAccuracy(): DimensionalNumber
    {
        return $this->accuracy;
    }

    /**
     * Базы действия.
     *
     * @return array<int, ActionCharacteristicBase> Список.
     */
    public function getActionCharacteristics(): array
    {
        return $this->actionCharacteristics;
    }

    /**
     * Падение силы.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getFalloff(): ?DimensionalNumber
    {
        return $this->falloff;
    }

    /**
     * Польза уклонения.
     *
     * @return int|null Число или null.
     */
    public function getDodgeBenefit(): ?int
    {
        return $this->dodgeBenefit;
    }
}
