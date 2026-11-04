<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Узел характеристики с модификатором.
 */
final class CharacteristicNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param string $characteristicCode Код характеристики.
     * @param int $modifier Модификатор.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $modifier,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'characteristic';
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
     * Смещение.
     *
     * @return int Значение.
     */
    public function getModifier(): int
    {
        return $this->modifier;
    }
}
