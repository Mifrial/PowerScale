<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Блок грантов одного уровня способности.
 */
final class AbilityGrantBlock
{
    /**
     * Создаёт блок.
     *
     * @param int $level Уровень.
     * @param array<int, AbilityGrant> $grants Гранты.
     *
     * @return void
     * @param mixed $level Поле.
     * @param mixed $grants Поле.
     */
    public function __construct(
        private readonly int $level,
        private readonly array $grants,
    ) {
    }

    /**
     * Уровень блока.
     *
     * @return int Уровень.
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Гранты блока.
     *
     * @return array<int, AbilityGrant> Список.
     */
    public function getGrants(): array
    {
        return $this->grants;
    }
}
