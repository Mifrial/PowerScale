<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_roll_score_adjust.
 */
final class CurrentActionRollScoreAdjustEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $oneDelta Сдвиг единицы.
     * @param int $faceDelta Сдвиг грани.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int $oneDelta,
        private readonly int $faceDelta,
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
        return 'current_action_roll_score_adjust';
    }

    /**
     * Сдвиг единицы.
     *
     * @return int Значение.
     */
    public function getOneDelta(): int
    {
        return $this->oneDelta;
    }

    /**
     * Сдвиг грани.
     *
     * @return int Значение.
     */
    public function getFaceDelta(): int
    {
        return $this->faceDelta;
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
