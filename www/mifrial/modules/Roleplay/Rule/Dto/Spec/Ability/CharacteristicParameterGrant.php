<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант параметра характеристики.
 */
final class CharacteristicParameterGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $characteristicCode Поле.
     * @param string $parameterCode Поле.
     * @param int $perUnit Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly string $parameterCode,
        private readonly int $perUnit,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'characteristic_parameter';
    }

    /**
     * Код характеристики.
     *
     * @return string Значение.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
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

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
