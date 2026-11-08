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
     * @param int|null $durability Прочность или абсолютная применимость.
     * @param string|null $sourceCode Источник.
     * @param bool $protectsInternal Держит ли внутренний урон.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalNumber $defense,
        private readonly ?int $durability,
        private readonly ?string $sourceCode,
        private readonly bool $protectsInternal = false,
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
     * @return int|null Число или null.
     */
    public function getDurability(): ?int
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

    /**
     * Держит ли слой внутренний урон.
     *
     * @return bool Значение.
     */
    public function isProtectsInternal(): bool
    {
        return $this->protectsInternal;
    }
}
