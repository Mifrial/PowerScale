<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Множитель цены других модификаторов.
 */
final class ItemModifierPriceScale
{
    /**
     * Создаёт множитель.
     *
     * @param string $typeCode Тип модификатора.
     * @param int|float $factor Множитель.
     * @param bool $increasingOnly Только рост цены.
     *
     * @return void
     */
    public function __construct(
        private readonly string $typeCode,
        private readonly int|float $factor,
        private readonly bool $increasingOnly,
    ) {
    }

    /**
     * Тип.
     *
     * @return string Код.
     */
    public function getTypeCode(): string
    {
        return $this->typeCode;
    }

    /**
     * Множитель.
     *
     * @return int|float Число.
     */
    public function getFactor(): int|float
    {
        return $this->factor;
    }

    /**
     * Только рост цены.
     *
     * @return bool true, если increasing_only.
     */
    public function isIncreasingOnly(): bool
    {
        return $this->increasingOnly;
    }
}
