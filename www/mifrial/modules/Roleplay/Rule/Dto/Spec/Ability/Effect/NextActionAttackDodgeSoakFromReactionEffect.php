<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка next_action_attack_dodge_soak_from_reaction.
 */
final class NextActionAttackDodgeSoakFromReactionEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param ?int $maxTotalActionCost Потолок ОД.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $maxTotalActionCost,
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
        return 'next_action_attack_dodge_soak_from_reaction';
    }

    /**
     * Потолок ОД.
     *
     * @return ?int Значение.
     */
    public function getMaxTotalActionCost(): ?int
    {
        return $this->maxTotalActionCost;
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
