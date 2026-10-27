<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Боеприпасы профиля: ничего, сам предмет или отдельный патрон.
 */
final class WeaponAmmunition
{
    /**
     * Создаёт описание.
     *
     * @param string $mode Режим none, self или item.
     * @param array<int, string> $keywordCodes Коды ключевых слов патрона.
     *
     * @return void
     */
    public function __construct(
        private readonly string $mode,
        private readonly array $keywordCodes,
    ) {
    }

    /**
     * Режим.
     *
     * @return string Код.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Коды ключевых слов.
     *
     * @return array<int, string> Список.
     */
    public function getKeywordCodes(): array
    {
        return $this->keywordCodes;
    }
}
