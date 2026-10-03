<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_attack_dodge_soak.
 */
final class CurrentActionAttackDodgeSoakEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $sizeDelta Сдвиг размера.
     * @param ?int $ignoreAtSr Игнор при успехе.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int $sizeDelta,
        private readonly ?int $ignoreAtSr,
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
        return 'current_action_attack_dodge_soak';
    }

    /**
     * Сдвиг размера.
     *
     * @return int Значение.
     */
    public function getSizeDelta(): int
    {
        return $this->sizeDelta;
    }

    /**
     * Игнор при успехе.
     *
     * @return ?int Значение.
     */
    public function getIgnoreAtSr(): ?int
    {
        return $this->ignoreAtSr;
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
