<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Эффект заклинания: наложенное состояние на цель.
 */
final class SpellStateEffect implements SpellEffect
{
    /**
     * Создаёт эффект.
     *
     * @param string $stateCode Код состояния.
     * @param SpellEffectSource|null $value Источник единиц состояния или null.
     * @param int|null $max Потолок единиц или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $stateCode,
        private readonly ?SpellEffectSource $value,
        private readonly ?int $max,
    ) {
    }

    /**
     * Тип эффекта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'state';
    }

    /**
     * Код состояния.
     *
     * @return string Код.
     */
    public function getStateCode(): string
    {
        return $this->stateCode;
    }

    /**
     * Источник единиц состояния.
     *
     * @return SpellEffectSource|null Источник или null.
     */
    public function getValue(): ?SpellEffectSource
    {
        return $this->value;
    }

    /**
     * Потолок единиц.
     *
     * @return int|null Потолок или null.
     */
    public function getMax(): ?int
    {
        return $this->max;
    }
}
