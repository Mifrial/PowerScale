<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Затухание по проверке характеристики.
 */
final class CheckDecay implements StateDecay
{
    /**
     * Создаёт затухание.
     *
     * @param string $characteristicCode Характеристика.
     *
     * @return void
     */
    public function __construct(private readonly string $characteristicCode)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return 'check';
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
}
