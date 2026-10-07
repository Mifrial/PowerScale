<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Одна запись преимущества или помехи: источник и дельта кубов.
 */
final class RollAdvantage
{
    /**
     * Собирает запись.
     *
     * @param string|null $sourceCode Источник; null — отдельная запись, не схлопывается с другими null.
     * @param int $delta Дельта кубов. Плюс — преимущество, минус — помеха.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $sourceCode,
        private readonly int $delta,
    ) {
    }

    /**
     * Источник дельты.
     *
     * @return string|null Код или null.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }

    /**
     * Дельта кубов.
     *
     * @return int Целое.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }
}
