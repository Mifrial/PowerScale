<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Значение параметра на единицу.
 */
final class ParameterScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $parameterCode Поле.
     * @param mixed $perUnit Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $parameterCode,
        private readonly int $perUnit,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'parameter';
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
     * На единицу.
     *
     * @return int Значение.
     */
    public function getPerUnit(): int
    {
        return $this->perUnit;
    }
}
