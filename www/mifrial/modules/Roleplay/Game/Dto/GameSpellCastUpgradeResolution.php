<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Разрешённые улучшения каста и их суммарный эффект.
 */
final class GameSpellCastUpgradeResolution
{
    /**
     * Собирает результат.
     *
     * @param array<int, GameSpellCastAppliedUpgrade> $applied Применённые улучшения.
     * @param array<int, RollAdvantage> $advantages Преимущества сотворения.
     *
     * @return void
     */
    public function __construct(
        private readonly array $applied,
        private readonly array $advantages,
    ) {
    }

    /**
     * Применённые улучшения.
     *
     * @return array<int, GameSpellCastAppliedUpgrade> Список.
     */
    public function getApplied(): array
    {
        return $this->applied;
    }

    /**
     * Суммарная добавка ОД.
     *
     * @return int Число.
     */
    public function getActionPointDelta(): int
    {
        $total = 0;
        foreach ($this->applied as $upgrade) {
            $total += $upgrade->getActionPointDelta();
        }

        return $total;
    }

    /**
     * Суммарная добавка Требуемой мощи.
     *
     * @return int Число.
     */
    public function getRequiredPowerDelta(): int
    {
        $total = 0;
        foreach ($this->applied as $upgrade) {
            $total += $upgrade->getRequiredPowerDelta();
        }

        return $total;
    }

    /**
     * Суммарное пробитие сопротивления.
     *
     * @return int Число.
     */
    public function getResistancePenetration(): int
    {
        $total = 0;
        foreach ($this->applied as $upgrade) {
            $total += $upgrade->getResistancePenetration();
        }

        return $total;
    }

    /**
     * Преимущества сотворения.
     *
     * @return array<int, RollAdvantage> Список.
     */
    public function getAdvantages(): array
    {
        return $this->advantages;
    }

    /**
     * Улучшений нет.
     *
     * @return bool true, если список пуст.
     */
    public function isEmpty(): bool
    {
        return $this->applied === [];
    }
}
