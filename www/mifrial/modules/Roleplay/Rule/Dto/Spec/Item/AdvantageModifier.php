<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Помеха или преимущество предмета.
 */
final class AdvantageModifier
{
    /**
     * Создаёт вклад.
     *
     * @param string|null $sourceCode Источник.
     * @param string|null $sourceLabel Подпись.
     * @param int $delta Величина.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $sourceCode,
        private readonly ?string $sourceLabel,
        private readonly int $delta,
    ) {
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

    /**
     * Подпись.
     *
     * @return string|null Текст или null.
     */
    public function getSourceLabel(): ?string
    {
        return $this->sourceLabel;
    }

    /**
     * Величина.
     *
     * @return int Число.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }
}
