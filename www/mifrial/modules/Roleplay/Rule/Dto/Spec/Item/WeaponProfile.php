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
     * @param string $type Strike, throw или shoot.
     * @param DistanceRange $distance Диапазон дистанции.
     * @param WeaponDamage $damage Урон.
     * @param DimensionalFormula $penetration Пробитие.
     * @param DimensionalNumber $accuracy Точность.
     * @param array<int, ActionCharacteristicBase> $actionCharacteristics Базы действия.
     * @param DistanceValue|null $falloff Дальнобойность.
     * @param int|null $dodgeBenefit Польза уклонения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $type,
        private readonly DistanceRange $distance,
        private readonly WeaponDamage $damage,
        private readonly DimensionalFormula $penetration,
        private readonly DimensionalNumber $accuracy,
        private readonly array $actionCharacteristics,
        private readonly ?DistanceValue $falloff,
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
     * Диапазон дистанции.
     *
     * @return DistanceRange Диапазон.
     */
    public function getDistance(): DistanceRange
    {
        return $this->distance;
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
     * Дальнобойность.
     *
     * @return DistanceValue|null Значение или null.
     */
    public function getFalloff(): ?DistanceValue
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
