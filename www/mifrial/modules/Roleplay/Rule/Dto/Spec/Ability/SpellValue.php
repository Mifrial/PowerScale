<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Мощь или контроль: размерное число либо параметр.
 */
final class SpellValue
{
    /**
     * Создаёт значение.
     *
     * @param DimensionalNumber|null $number Число.
     * @param string|null $parameterCode Параметр.
     *
     * @return void
     */
    public function __construct(
        private readonly ?DimensionalNumber $number,
        private readonly ?string $parameterCode,
    ) {
    }

    /**
     * Число.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getNumber(): ?DimensionalNumber
    {
        return $this->number;
    }

    /**
     * Параметр.
     *
     * @return string|null Код или null.
     */
    public function getParameterCode(): ?string
    {
        return $this->parameterCode;
    }
}
