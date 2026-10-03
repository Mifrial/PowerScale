<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Грант характеристики.
 */
final class CharacteristicGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $characteristicCode Поле.
     * @param CharacteristicNumber $value Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly CharacteristicNumber $value,
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
        return 'characteristic';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Значение.
     *
     * @return CharacteristicNumber Значение.
     */
    public function getValue(): CharacteristicNumber
    {
        return $this->value;
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
