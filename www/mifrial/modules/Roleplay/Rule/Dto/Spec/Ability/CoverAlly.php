<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Прикрытие союзника.
 */
final class CoverAlly
{
    /**
     * Создаёт прикрытие.
     *
     * @param int $circumstanceDelta Помеха обстоятельства.
     *
     * @return void
     */
    public function __construct(private readonly int $circumstanceDelta)
    {
    }

    /**
     * Помеха.
     *
     * @return int Число.
     */
    public function getCircumstanceDelta(): int
    {
        return $this->circumstanceDelta;
    }
}
