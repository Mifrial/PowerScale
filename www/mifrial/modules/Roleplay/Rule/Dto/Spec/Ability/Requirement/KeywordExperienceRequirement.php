<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Минимум опыта признаков: сумма цен способностей со всеми перечисленными признаками.
 */
final class KeywordExperienceRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param array<int, string> $keywordCodes Признаки, которые должны быть у способности.
     * @param int $min Минимум опыта.
     *
     * @return void
     */
    public function __construct(
        private readonly array $keywordCodes,
        private readonly int $min,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'keyword_experience';
    }

    /**
     * Признаки.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordCodes(): array
    {
        return $this->keywordCodes;
    }

    /**
     * Минимум.
     *
     * @return int Число.
     */
    public function getMin(): int
    {
        return $this->min;
    }
}
