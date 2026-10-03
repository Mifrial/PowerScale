<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант способности.
 */
final class AbilityCodeGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $abilityCode Поле.
     * @param int $level Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly int $level,
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
        return 'ability';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getAbilityCode(): string
    {
        return $this->abilityCode;
    }

    /**
     * Уровень.
     *
     * @return int Значение.
     */
    public function getLevel(): int
    {
        return $this->level;
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
