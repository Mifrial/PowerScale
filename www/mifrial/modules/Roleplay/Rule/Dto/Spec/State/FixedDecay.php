<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Фиксированное затухание.
 */
final class FixedDecay implements StateDecay
{
    /**
     * Создаёт затухание.
     *
     * @param int $value Число.
     *
     * @return void
     */
    public function __construct(private readonly int $value)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return 'fixed';
    }

    /**
     * Число.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }
}
