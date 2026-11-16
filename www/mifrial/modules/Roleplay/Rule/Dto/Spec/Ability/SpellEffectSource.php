<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Источник величины эффекта заклинания.
 */
final class SpellEffectSource
{
    /**
     * Создаёт источник.
     *
     * @param string $from Откуда: power, sustain_power, control или parameter.
     * @param string|null $parameterCode Код активационного параметра.
     * @param DimensionalNumber|null $reference Опорное значение для разницы.
     *
     * @return void
     */
    public function __construct(
        private readonly string $from,
        private readonly ?string $parameterCode,
        private readonly ?DimensionalNumber $reference,
    ) {
    }

    /**
     * Источник.
     *
     * @return string Код.
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Код параметра.
     *
     * @return string|null Код или null.
     */
    public function getParameterCode(): ?string
    {
        return $this->parameterCode;
    }

    /**
     * Опорное значение.
     *
     * @return DimensionalNumber|null Пара или null.
     */
    public function getReference(): ?DimensionalNumber
    {
        return $this->reference;
    }
}
