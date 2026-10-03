<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Способность trait, feature или skill.
 */
final class AbilityPlainSpec implements AbilitySpec
{
    /**
     * Создаёт spec.
     *
     * @param string|null $abilityType Ветка или null.
     * @param AbilityBase $base Общие поля.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $abilityType,
        private readonly AbilityBase $base,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'ability';
    }

    /**
     * Ветка.
     *
     * @return string|null Код или null.
     */
    public function getAbilityType(): ?string
    {
        return $this->abilityType;
    }

    /**
     * Блоки грантов.
     *
     * @return array<int, AbilityGrantBlock> Блоки.
     */
    public function getGrantBlocks(): array
    {
        return $this->base->getGrantBlocks();
    }

    /**
     * Общие поля.
     *
     * @return AbilityBase База.
     */
    public function getBase(): AbilityBase
    {
        return $this->base;
    }
}
