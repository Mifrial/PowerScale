<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант признака.
 */
final class KeywordGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $keywordCode Поле.
     * @param bool $remove Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $keywordCode,
        private readonly bool $remove,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'keyword';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getKeywordCode(): string
    {
        return $this->keywordCode;
    }

    /**
     * Снять признак.
     *
     * @return bool Значение.
     */
    public function isRemove(): bool
    {
        return $this->remove;
    }

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
