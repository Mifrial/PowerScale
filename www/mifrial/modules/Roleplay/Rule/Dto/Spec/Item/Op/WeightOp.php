<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка weight.
 */
final class WeightOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int|float|null $factor Множитель.
     * @param int|float|null $addKg Добавка веса.
     *
     * @return void
     */
    public function __construct(
        private readonly int|float|null $factor,
        private readonly int|float|null $addKg,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'weight';
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
     * Добавка веса.
     *
     * @return int|float|null Значение.
     */
    public function getAddKg(): int|float|null
    {
        return $this->addKg;
    }
}
