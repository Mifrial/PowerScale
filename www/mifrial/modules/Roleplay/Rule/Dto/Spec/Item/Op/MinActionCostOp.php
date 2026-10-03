<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка min_action_cost.
 */
final class MinActionCostOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int $min Минимум ОД.
     *
     * @return void
     */
    public function __construct(
        private readonly int $min,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'min_action_cost';
    }

    /**
     * Минимум ОД.
     *
     * @return int Значение.
     */
    public function getMin(): int
    {
        return $this->min;
    }
}
