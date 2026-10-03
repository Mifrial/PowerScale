<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка durability.
 */
final class DurabilityOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param ?int $delta Сдвиг.
     * @param ?int $addSize Размер.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $delta,
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
        return 'durability';
    }

    /**
     * Сдвиг.
     *
     * @return ?int Значение.
     */
    public function getDelta(): ?int
    {
        return $this->delta;
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
