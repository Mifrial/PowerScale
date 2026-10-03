<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка min_strength.
 */
final class MinStrengthOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     *
     * @return void
     */
    public function __construct(
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
        return 'min_strength';
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
