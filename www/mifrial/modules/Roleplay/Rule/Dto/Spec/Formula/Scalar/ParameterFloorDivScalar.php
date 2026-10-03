<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Целая часть параметра.
 */
final class ParameterFloorDivScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $parameterCode Поле.
     * @param mixed $divisor Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $parameterCode,
        private readonly int $divisor,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'parameter_floor_div';
    }

    /**
     * Код параметра.
     *
     * @return string Значение.
     */
    public function getParameterCode(): string
    {
        return $this->parameterCode;
    }

    /**
     * Делитель.
     *
     * @return int Значение.
     */
    public function getDivisor(): int
    {
        return $this->divisor;
    }
}
