<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Защита от атаки, которой владеет защитник.
 */
final class KnownAttackDefense
{
    /**
     * Создаёт защиту.
     *
     * @param int $delta Сдвиг.
     * @param string|null $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly ?string $sourceCode,
    ) {
    }

    /**
     * Сдвиг.
     *
     * @return int Число.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Источник.
     *
     * @return string|null Код или null.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
