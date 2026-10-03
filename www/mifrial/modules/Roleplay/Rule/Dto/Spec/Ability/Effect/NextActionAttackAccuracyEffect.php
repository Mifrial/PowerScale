<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка next_action_attack_accuracy.
 */
final class NextActionAttackAccuracyEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     * @param bool $sameTarget Та же цель.
     * @param ?string $targetKey Ключ цели.
     * @param AttackScope $scope Область.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly bool $sameTarget,
        private readonly ?string $targetKey,
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
        return 'next_action_attack_accuracy';
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
     * Та же цель.
     *
     * @return bool Значение.
     */
    public function isSameTarget(): bool
    {
        return $this->sameTarget;
    }

    /**
     * Ключ цели.
     *
     * @return ?string Значение.
     */
    public function getTargetKey(): ?string
    {
        return $this->targetKey;
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
