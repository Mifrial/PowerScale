<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка strength_penalty.
 */
final class StrengthPenaltyOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param ?int $add Слагаемое.
     * @param ?int $set Установка.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $add,
        private readonly ?int $set,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'strength_penalty';
    }

    /**
     * Слагаемое.
     *
     * @return ?int Значение.
     */
    public function getAdd(): ?int
    {
        return $this->add;
    }

    /**
     * Установка.
     *
     * @return ?int Значение.
     */
    public function getSet(): ?int
    {
        return $this->set;
    }
}
