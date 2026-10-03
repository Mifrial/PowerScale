<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Нужно число способностей с признаком.
 */
final class HasAbilityKeywordRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $keywordCode Признак.
     * @param int $minCount Минимум.
     * @param array<int, string> $excludeKeywordCodes Исключения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $keywordCode,
        private readonly int $minCount,
        private readonly array $excludeKeywordCodes,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'has_ability_keyword';
    }

    /**
     * Признак.
     *
     * @return string Код.
     */
    public function getKeywordCode(): string
    {
        return $this->keywordCode;
    }

    /**
     * Минимум.
     *
     * @return int Число.
     */
    public function getMinCount(): int
    {
        return $this->minCount;
    }

    /**
     * Исключения.
     *
     * @return array<int, string> Коды.
     */
    public function getExcludeKeywordCodes(): array
    {
        return $this->excludeKeywordCodes;
    }
}
