<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка next_spell_cast_difficulty.
 */
final class NextSpellCastDifficultyEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     * @param ?string $sourceCode Источник.
     * @param int $maxTotalActionCost Потолок ОД.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly ?string $sourceCode,
        private readonly int $maxTotalActionCost,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'next_spell_cast_difficulty';
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
     * @return ?string Значение.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }

    /**
     * Потолок ОД.
     *
     * @return int Значение.
     */
    public function getMaxTotalActionCost(): int
    {
        return $this->maxTotalActionCost;
    }
}
