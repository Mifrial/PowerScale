<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Группа способностей: только предел выбора.
 */
final class AbilityGroupSpec implements AbilitySpec
{
    /**
     * Создаёт spec.
     *
     * @param int $selectLimit Предел выбора.
     *
     * @return void
     */
    public function __construct(
        private readonly int $selectLimit,
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
     * Предел выбора.
     *
     * @return int Число.
     */
    public function getSelectLimit(): int
    {
        return $this->selectLimit;
    }

    /**
     * У группы нет грантов.
     *
     * @return array<int, AbilityGrantBlock> Пустой список.
     */
    public function getGrantBlocks(): array
    {
        return [];
    }
}
