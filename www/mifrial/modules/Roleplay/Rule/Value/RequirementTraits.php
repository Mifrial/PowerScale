<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Признаки персонажа, способностей и семей оружия для проверки требований.
 */
final class RequirementTraits
{
    /**
     * Создаёт факты.
     *
     * @param array<string, array<int, string>> $abilityKeywords Признаки способностей по коду.
     * @param array<int, string> $keywords Признаки персонажа.
     * @param array<string, array<int, string>> $weaponFamiliesByKeyword Признаки семей оружия.
     * @param array<string, int> $weaponMasteryLevels Уровни владения семьями оружия.
     *
     * @return void
     */
    public function __construct(
        public readonly array $abilityKeywords = [],
        public readonly array $keywords = [],
        public readonly array $weaponFamiliesByKeyword = [],
        public readonly array $weaponMasteryLevels = [],
    ) {
    }
}
