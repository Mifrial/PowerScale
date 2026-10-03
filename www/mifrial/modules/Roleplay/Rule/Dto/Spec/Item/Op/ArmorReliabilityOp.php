<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка armor_reliability.
 */
final class ArmorReliabilityOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param ?int $set Установка.
     * @param ?int $add Слагаемое.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $set,
        private readonly ?int $add,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'armor_reliability';
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

    /**
     * Слагаемое.
     *
     * @return ?int Значение.
     */
    public function getAdd(): ?int
    {
        return $this->add;
    }
}
