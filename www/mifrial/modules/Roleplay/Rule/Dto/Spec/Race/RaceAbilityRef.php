<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ссылка способности расы или вида.
 */
final class RaceAbilityRef
{
    /**
     * Создаёт ссылку.
     *
     * @param string $abilityCode Код.
     * @param bool $automatic Автополучение.
     * @param array<string, DimensionalNumber> $parameters Параметры.
     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly bool $automatic,
        private readonly array $parameters,
    ) {
    }

    /**
     * Код способности.
     *
     * @return string Код.
     */
    public function getAbilityCode(): string
    {
        return $this->abilityCode;
    }

    /**
     * Автополучение.
     *
     * @return bool true, если automatic.
     */
    public function isAutomatic(): bool
    {
        return $this->automatic;
    }

    /**
     * Параметры ссылки.
     *
     * @return array<string, DimensionalNumber> Словарь.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
