<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Нужна способность не ниже уровня.
 */
final class HasAbilityRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $abilityCode Способность.
     * @param int|null $minLevel Минимум уровня.
     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly ?int $minLevel,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'has_ability';
    }

    /**
     * Способность.
     *
     * @return string Код.
     */
    public function getAbilityCode(): string
    {
        return $this->abilityCode;
    }

    /**
     * Минимум уровня.
     *
     * @return int|null Уровень или null.
     */
    public function getMinLevel(): ?int
    {
        return $this->minLevel;
    }
}
