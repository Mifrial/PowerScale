<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Слот сопротивления брони.
 */
final class ResistanceSlot
{
    /**
     * Создаёт слот.
     *
     * @param string|null $damageTypeCode Тип урона.
     * @param DimensionalNumber $value Значение.
     * @param int $durability Прочность.
     * @param string|null $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $damageTypeCode,
        private readonly DimensionalNumber $value,
        private readonly int $durability,
        private readonly ?string $sourceCode,
    ) {
    }

    /**
     * Тип урона.
     *
     * @return string|null Код или null.
     */
    public function getDamageTypeCode(): ?string
    {
        return $this->damageTypeCode;
    }

    /**
     * Значение.
     *
     * @return DimensionalNumber Число.
     */
    public function getValue(): DimensionalNumber
    {
        return $this->value;
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
