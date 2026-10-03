<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Ветка characteristic_modify.
 */
final class CharacteristicModifyEffect implements StateEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $characteristicCode Характеристика.
     * @param int $amount Величина.
     * @param bool $perUnit На единицу.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $amount,
        private readonly bool $perUnit,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'characteristic_modify';
    }

    /**
     * Характеристика.
     *
     * @return string Значение.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Величина.
     *
     * @return int Значение.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * На единицу.
     *
     * @return bool Значение.
     */
    public function isPerUnit(): bool
    {
        return $this->perUnit;
    }
}
