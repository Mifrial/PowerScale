<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка next_action_attack_cost.
 */
final class NextActionAttackCostEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $resourceCode Ресурс.
     * @param int $delta Сдвиг.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int $delta,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'next_action_attack_cost';
    }

    /**
     * Ресурс.
     *
     * @return string Значение.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
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
}
