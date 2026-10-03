<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ветка literal.
 */
final class LiteralDistance implements ProcessDistance
{
    /**
     * Создаёт ветку.
     *
     * @param DimensionalNumber $value Число.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalNumber $value,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'literal';
    }

    /**
     * Число.
     *
     * @return DimensionalNumber Значение.
     */
    public function getValue(): DimensionalNumber
    {
        return $this->value;
    }
}
