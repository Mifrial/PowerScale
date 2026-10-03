<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Применимость модификатора по признакам.
 */
final class ItemModifierApplies
{
    /**
     * Создаёт применимость.
     *
     * @param array<int, string> $keywordAll Все признаки.
     * @param array<int, string> $keywordAny Любой признак.
     * @param array<int, string> $keywordNone Запрет.
     *
     * @return void
     */
    public function __construct(
        private readonly array $keywordAll,
        private readonly array $keywordAny,
        private readonly array $keywordNone,
    ) {
    }

    /**
     * Все признаки.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordAll(): array
    {
        return $this->keywordAll;
    }

    /**
     * Любой признак.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordAny(): array
    {
        return $this->keywordAny;
    }

    /**
     * Запрет.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordNone(): array
    {
        return $this->keywordNone;
    }
}
