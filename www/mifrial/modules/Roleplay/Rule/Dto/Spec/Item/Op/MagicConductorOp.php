<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка magic_conductor.
 */
final class MagicConductorOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int $value Величина.
     *
     * @return void
     */
    public function __construct(
        private readonly int $value,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'magic_conductor';
    }

    /**
     * Величина.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }
}
