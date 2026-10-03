<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_roll_face_remap.
 */
final class CurrentActionRollFaceRemapEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $from Исходная грань.
     * @param int $to Новая грань.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int $from,
        private readonly int $to,
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
        return 'current_action_roll_face_remap';
    }

    /**
     * Исходная грань.
     *
     * @return int Значение.
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * Новая грань.
     *
     * @return int Значение.
     */
    public function getTo(): int
    {
        return $this->to;
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
