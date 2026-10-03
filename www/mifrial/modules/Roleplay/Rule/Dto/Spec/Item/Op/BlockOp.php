<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка block.
 */
final class BlockOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int|float|null $factor Множитель.
     * @param ?int $add Слагаемое.
     * @param ?int $addSize Размер.
     *
     * @return void
     */
    public function __construct(
        private readonly int|float|null $factor,
        private readonly ?int $add,
        private readonly ?int $addSize,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'block';
    }

    /**
     * Множитель.
     *
     * @return int|float|null Значение.
     */
    public function getFactor(): int|float|null
    {
        return $this->factor;
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
     * Размер.
     *
     * @return ?int Значение.
     */
    public function getAddSize(): ?int
    {
        return $this->addSize;
    }
}
