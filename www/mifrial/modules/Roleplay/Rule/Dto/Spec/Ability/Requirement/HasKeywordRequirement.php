<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Нужен признак.
 */
final class HasKeywordRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $keywordCode Признак.
     *
     * @return void
     */
    public function __construct(private readonly string $keywordCode)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'has_keyword';
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
}
