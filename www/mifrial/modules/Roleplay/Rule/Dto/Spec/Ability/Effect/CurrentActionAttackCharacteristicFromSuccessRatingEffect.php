<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_attack_characteristic_from_success_rating.
 */
final class CurrentActionAttackCharacteristicFromSuccessRatingEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $floorDiv Делитель.
     * @param int $cap Потолок.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int $floorDiv,
        private readonly int $cap,
        private readonly AttackScope $scope,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'current_action_attack_characteristic_from_success_rating';
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
     * @return int Значение.
     */
    public function getCap(): int
    {
        return $this->cap;
    }

    /**
     * Область.
     *
     * @return AttackScope Значение.
     */
    public function getScope(): AttackScope
    {
        return $this->scope;
    }
}
