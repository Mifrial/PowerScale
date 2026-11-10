<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSaturation;

/**
 * Источник насыщения у изученной способности кастера.
 */
final class GameSpellCastSaturationSource
{
    /**
     * Создаёт источник.
     *
     * @param string $ruleCode Код способности.
     * @param SpellSaturation $saturation Форма насыщения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly SpellSaturation $saturation,
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
     * Минимум успеха.
     *
     * @return int Число.
     */
    public function getMinRating(): int
    {
        return $this->saturation->getMinRating();
    }

    /**
     * Успех на шаг.
     *
     * @return int Число.
     */
    public function getRatingPerStep(): int
    {
        return $this->saturation->getRatingPerStep();
    }

    /**
     * Мощь на шаг.
     *
     * @return int Число.
     */
    public function getPowerPerStep(): int
    {
        return $this->saturation->getPowerPerStep();
    }
}
