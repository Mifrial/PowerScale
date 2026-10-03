<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Способность-процесс.
 */
final class AbilityProcessSpec implements AbilitySpec
{
    /**
     * Создаёт spec.
     *
     * @param AbilityBase $base Общие поля.
     * @param ProcessSpec $process Тело.
     *
     * @return void
     */
    public function __construct(
        private readonly AbilityBase $base,
        private readonly ProcessSpec $process,
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
     * Тело процесса.
     *
     * @return ProcessSpec Тело.
     */
    public function getProcess(): ProcessSpec
    {
        return $this->process;
    }
}
