<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Реакция поддержки: какие стороны можно поддержать, насколько и каким оружием.
 */
final class SupportModifier
{
    /**
     * Создаёт узел поддержки.
     *
     * @param array<int, string> $sides Кому можно помочь: attacker или defender.
     * @param int $advantage Преимущество на проверку выбранной стороны.
     * @param string|null $profile Профиль оружия поддержки или null — любой.
     * @param DimensionalNumber|null $minMastery Минимум мастерства выбранного оружия или null.
     * @param string $source Источник преимущества.
     *
     * @return void
     */
    public function __construct(
        private readonly array $sides,
        private readonly int $advantage,
        private readonly ?string $profile = null,
        private readonly ?DimensionalNumber $minMastery = null,
        private readonly string $source = 'circumstances',
    ) {
    }

    /**
     * Разрешённые стороны.
     *
     * @return array<int, string> attacker и/или defender.
     */
    public function getSides(): array
    {
        return $this->sides;
    }

    /**
     * Преимущество.
     *
     * @return int Кубы.
     */
    public function getAdvantage(): int
    {
        return $this->advantage;
    }

    /**
     * Профиль оружия поддержки.
     *
     * @return string|null strike, throw, shoot или null.
     */
    public function getProfile(): ?string
    {
        return $this->profile;
    }

    /**
     * Минимум мастерства выбранного оружия.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getMinMastery(): ?DimensionalNumber
    {
        return $this->minMastery;
    }

    /**
     * Источник преимущества.
     *
     * @return string Код источника.
     */
    public function getSource(): string
    {
        return $this->source;
    }
}
