<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Узкий снимок для оценки механик: уровни способностей, признаки и расовые коды.
 */
final class MechanicState
{
    /**
     * Собирает снимок.
     *
     * @param array<string, int> $abilityLevels Уровни по коду способности.
     * @param array<string, array<int, string>> $abilityKeywords Признаки по коду способности.
     * @param array<int, string> $racialAbilityCodes Коды способностей выбранной расы.
     *
     * @return void
     */
    public function __construct(
        private readonly array $abilityLevels,
        private readonly array $abilityKeywords,
        private readonly array $racialAbilityCodes,
    ) {
    }

    /**
     * Уровни способностей.
     *
     * @return array<string, int> Код → уровень, в порядке вставки.
     */
    public function getAbilityLevels(): array
    {
        return $this->abilityLevels;
    }

    /**
     * Признаки способностей.
     *
     * @return array<string, array<int, string>> Код способности → коды признаков.
     */
    public function getAbilityKeywords(): array
    {
        return $this->abilityKeywords;
    }

    /**
     * Коды способностей расы.
     *
     * @return array<int, string> Коды.
     */
    public function getRacialAbilityCodes(): array
    {
        return $this->racialAbilityCodes;
    }
}
