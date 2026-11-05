<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Одна помеха прикрывающего: величина и источник отдельно.
 */
final class DefenseAdvantage
{
    /**
     * Создаёт запись.
     *
     * @param int $delta Дельта кубов; минус — помеха.
     * @param string $sourceCode Код источника.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly string $sourceCode,
    ) {
    }

    /**
     * Дельта кубов.
     *
     * @return int Число.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Код источника.
     *
     * @return string Код.
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }
}
