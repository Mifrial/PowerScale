<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;

/**
 * Цена за единицу параметра.
 */
final class ParameterZone implements AbilityZone
{
    /**
     * Создаёт цену.
     *
     * @param string $parameterCode Параметр.
     * @param int $perUnit Цена единицы.
     *
     * @return void
     */
    public function __construct(
        private readonly string $parameterCode,
        private readonly int $perUnit,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'parameter';
    }

    /**
     * Параметр.
     *
     * @return string Код.
     */
    public function getParameterCode(): string
    {
        return $this->parameterCode;
    }

    /**
     * Цена единицы.
     *
     * @return int Цена.
     */
    public function getPerUnit(): int
    {
        return $this->perUnit;
    }
}
