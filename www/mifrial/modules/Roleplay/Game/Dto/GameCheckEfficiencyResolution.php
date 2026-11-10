<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Эффективность проверки и вклады её источников.
 */
final class GameCheckEfficiencyResolution
{
    /**
     * Собирает результат.
     *
     * @param CharacteristicNumber $efficiency Эффективность.
     * @param list<array{source_code: string, source_label: string, delta: int}> $adjustments Вклады.
     * @param bool $explicit Признак явно заданной эффективности.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacteristicNumber $efficiency,
        private readonly array $adjustments,
        private readonly bool $explicit,
    ) {
    }

    /**
     * Эффективность.
     *
     * @return CharacteristicNumber Значение.
     */
    public function getEfficiency(): CharacteristicNumber
    {
        return $this->efficiency;
    }

    /**
     * Вклады источников.
     *
     * @return list<array{source_code: string, source_label: string, delta: int}> Вклады.
     */
    public function getAdjustments(): array
    {
        return $this->adjustments;
    }

    /**
     * Признак явной эффективности.
     *
     * @return bool true, если значение задано источником.
     */
    public function isExplicit(): bool
    {
        return $this->explicit;
    }
}
