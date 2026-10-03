<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_attack_accuracy.
 */
final class CurrentActionAttackAccuracyEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
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
        return 'current_action_attack_accuracy';
    }

    /**
     * Сдвиг.
     *
     * @return int Значение.
     */
    public function getDelta(): int
    {
        return $this->delta;
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
