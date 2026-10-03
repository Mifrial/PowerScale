<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Сложность следующего сотворения после насыщения.
 */
final class NextCastDifficulty
{
    /**
     * Создаёт сложность.
     *
     * @param int $minRemainingRating Остаток успеха.
     * @param int $delta Сдвиг.
     * @param string|null $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minRemainingRating,
        private readonly int $delta,
        private readonly ?string $sourceCode,
    ) {
    }

    /**
     * Остаток успеха.
     *
     * @return int Число.
     */
    public function getMinRemainingRating(): int
    {
        return $this->minRemainingRating;
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
