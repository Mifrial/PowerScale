<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_attack_reach.
 */
final class CurrentActionAttackReachEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int|float $stepFraction Доля шага.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int|float $stepFraction,
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
        return 'current_action_attack_reach';
    }

    /**
     * Доля шага.
     *
     * @return int|float Значение.
     */
    public function getStepFraction(): int|float
    {
        return $this->stepFraction;
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
