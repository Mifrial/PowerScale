<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Слот защиты брони.
 */
final class DefenseSlot
{
    /**
     * Создаёт слот.
     *
     * @param DimensionalNumber $defense Защита.
     * @param int $durability Прочность.
     * @param string|null $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalNumber $defense,
        private readonly int $durability,
        private readonly ?string $sourceCode,
    ) {
    }

    /**
     * Защита.
     *
     * @return DimensionalNumber Число.
     */
    public function getDefense(): DimensionalNumber
    {
        return $this->defense;
    }

    /**
     * Прочность.
     *
     * @return int Число.
     */
    public function getDurability(): int
    {
        return $this->durability;
    }

    /**
     * Источник.
     *
     * @return string|null Код или null.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
