<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ActionComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\ProcessOperation;

/**
 * Способность-заклинание.
 */
final class AbilitySpellSpec implements AbilitySpec
{
    /**
     * Создаёт spec.
     *
     * @param AbilityBase $base Общие поля.
     * @param SpellSpec $spell Тело.
     * @param array<int, ActionComponent> $components Компоненты.
     * @param array<int, ProcessOperation> $operations Операции.
     *
     * @return void
     */
    public function __construct(
        private readonly AbilityBase $base,
        private readonly SpellSpec $spell,
        private readonly array $components,
        private readonly array $operations,
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
     * Блоки грантов.
     *
     * @return array<int, AbilityGrantBlock> Блоки.
     */
    public function getGrantBlocks(): array
    {
        return $this->base->getGrantBlocks();
    }

    /**
     * Тело заклинания.
     *
     * @return SpellSpec Тело.
     */
    public function getSpell(): SpellSpec
    {
        return $this->spell;
    }

    /**
     * Компоненты.
     *
     * @return array<int, ActionComponent> Список.
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Операции.
     *
     * @return array<int, ProcessOperation> Список.
     */
    public function getOperations(): array
    {
        return $this->operations;
    }
}
