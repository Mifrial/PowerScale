<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка advantage.
 */
final class AdvantageOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     * @param string $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly string $sourceCode,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'advantage';
    }

    /**
     * Сдвиг.
     *
     * @return int Значение.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Источник.
     *
     * @return string Значение.
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }
}
