<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Модификатор характеристики действия.
 */
final class ActionCharacteristicModifier
{
    /**
     * Создаёт модификатор.
     *
     * @param int $delta Смещение.
     * @param string|null $sourceCode Источник.
     * @param string|null $sourceLabel Подпись источника.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly ?string $sourceCode,
        private readonly ?string $sourceLabel,
    ) {
    }

    /**
     * Смещение.
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
}
