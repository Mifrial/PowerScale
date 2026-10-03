<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Размер характеристики.
 */
final class CharacteristicSizeScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $characteristicCode Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'characteristic_size';
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
}
