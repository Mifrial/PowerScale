<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ActionComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\ProcessOperation;

/**
 * Способность-действие.
 */
final class AbilityActionSpec implements AbilitySpec
{
    /**
     * Создаёт spec.
     *
     * @param AbilityBase $base Общие поля.
     * @param array<int, ActionComponent> $components Компоненты.
     * @param array<int, ProcessOperation> $operations Операции.
     *
     * @return void
     */
    public function __construct(
        private readonly AbilityBase $base,
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
     * Общие поля.
     *
     * @return AbilityBase База.
     */
    public function getBase(): AbilityBase
    {
        return $this->base;
    }

    /**
     * Компоненты действия.
     *
     * @return array<int, ActionComponent> Компоненты.
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Операции.
     *
     * @return array<int, ProcessOperation> Операции.
     */
    public function getOperations(): array
    {
        return $this->operations;
    }
}
