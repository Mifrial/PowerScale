<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Связь параметра с параметром другой способности.
 */
final class ParameterLink
{
    /**
     * Создаёт связь.
     *
     * @param string $abilityCode Способность.
     * @param string $parameterCode Параметр.
     * @param int $maxDelta Максимальное отличие.
     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly string $parameterCode,
        private readonly int $maxDelta,
    ) {
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
     * Параметр.
     *
     * @return string Код.
     */
    public function getParameterCode(): string
    {
        return $this->parameterCode;
    }

    /**
     * Максимальное отличие.
     *
     * @return int Число.
     */
    public function getMaxDelta(): int
    {
        return $this->maxDelta;
    }
}
