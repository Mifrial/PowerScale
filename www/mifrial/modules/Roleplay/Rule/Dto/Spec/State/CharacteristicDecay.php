<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Затухание по характеристике.
 */
final class CharacteristicDecay implements StateDecay
{
    /**
     * Создаёт затухание.
     *
     * @param string $characteristicCode Характеристика.
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
     * Вид.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return 'characteristic';
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
     * Модификатор.
     *
     * @return int Число.
     */
    public function getModifier(): int
    {
        return $this->modifier;
    }
}
