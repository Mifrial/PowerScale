<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка characteristic_modify.
 */
final class CharacteristicModifyOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param string $characteristicCode Характеристика.
     * @param ?int $add Слагаемое.
     * @param ?int $set Установка.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly ?int $add,
        private readonly ?int $set,
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
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Слагаемое.
     *
     * @return ?int Значение.
     */
    public function getAdd(): ?int
    {
        return $this->add;
    }

    /**
     * Установка.
     *
     * @return ?int Значение.
     */
    public function getSet(): ?int
    {
        return $this->set;
    }
}
