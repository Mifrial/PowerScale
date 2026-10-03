<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка attack_sr_from_previous.
 */
final class AttackSrFromPreviousEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $floorDiv Делитель.
     * @param string $cap Потолок.
     *
     * @return void
     */
    public function __construct(
        private readonly int $floorDiv,
        private readonly string $cap,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'attack_sr_from_previous';
    }

    /**
     * Делитель.
     *
     * @return int Значение.
     */
    public function getFloorDiv(): int
    {
        return $this->floorDiv;
    }

    /**
     * Потолок.
     *
     * @return string Значение.
     */
    public function getCap(): string
    {
        return $this->cap;
    }
}
