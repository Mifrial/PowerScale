<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Эффект заклинания: временное чувство.
 */
final class SpellSenseEffect implements SpellEffect
{
    /**
     * Создаёт эффект.
     *
     * @param string $senseCode Код чувства.
     * @param string|null $status Статус или null.
     * @param SpellEffectSource|null $radius Источник радиуса или null.
     * @param SpellEffectSource|null $value Источник значения или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $senseCode,
        private readonly ?string $status,
        private readonly ?SpellEffectSource $radius,
        private readonly ?SpellEffectSource $value,
    ) {
    }

    /**
     * Тип эффекта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'sense';
    }

    /**
     * Код чувства.
     *
     * @return string Код.
     */
    public function getSenseCode(): string
    {
        return $this->senseCode;
    }

    /**
     * Статус.
     *
     * @return string|null Статус или null.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Источник радиуса.
     *
     * @return SpellEffectSource|null Источник или null.
     */
    public function getRadius(): ?SpellEffectSource
    {
        return $this->radius;
    }

    /**
     * Источник значения.
     *
     * @return SpellEffectSource|null Источник или null.
     */
    public function getValue(): ?SpellEffectSource
    {
        return $this->value;
    }
}
