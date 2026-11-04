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
     * @param string|null $sourceLabel Подпись источника или null.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $sourceCode,
        private readonly int $delta,
        private readonly ?string $sourceLabel = null,
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
     * Подпись источника.
     *
     * @return string|null Подпись или null.
     */
    public function getSourceLabel(): ?string
    {
        return $this->sourceLabel;
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
