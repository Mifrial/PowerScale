<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\NextCastDifficulty;

/**
 * Источник снижения Сложности следующего сотворения.
 */
final class GameSpellCastNextCastSource
{
    /**
     * Создаёт источник.
     *
     * @param string $ruleCode Код способности.
     * @param NextCastDifficulty $nextCast Форма снижения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly NextCastDifficulty $nextCast,
    ) {
    }

    /**
     * Код способности.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Минимальный остаток успеха.
     *
     * @return int Число.
     */
    public function getMinRemainingRating(): int
    {
        return $this->nextCast->getMinRemainingRating();
    }

    /**
     * Сдвиг Сложности.
     *
     * @return int Число.
     */
    public function getDelta(): int
    {
        return $this->nextCast->getDelta();
    }

    /**
     * Источник сдвига.
     *
     * @return string|null Код или null.
     */
    public function getSourceCode(): ?string
    {
        return $this->nextCast->getSourceCode();
    }
}
