<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Проверка модификатора действия.
 */
final class ModifierCheck
{
    /**
     * Создаёт проверку.
     *
     * @param string $code Код проверки.
     * @param int $difficulty Сложность.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly int $difficulty,
    ) {
    }

    /**
     * Код проверки.
     *
     * @return string Значение.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Сложность.
     *
     * @return int Значение.
     */
    public function getDifficulty(): int
    {
        return $this->difficulty;
    }
}
