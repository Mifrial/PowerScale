<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies -- семь полей одного итога броска.

/**
 * Итог броска: спека после дефолтов, грани и успехи.
 */
final class RollResult
{
    /**
     * Собирает итог.
     *
     * @param RollSpec $spec Спека после дефолтов.
     * @param array<int, int> $rolls Все брошенные грани.
     * @param array<int, int> $successes Успехи по оставшимся граням.
     * @param array<int, int> $adjustedRolls Грани после сброса.
     * @param array<int, int> $droppedRolls Сброшенные грани.
     * @param int $totalSuccesses Сумма успехов.
     * @param array<int, string>|null $appliedNames Имена сработавших механик; null, если никто не сработал.
     *
     * @return void
     */
    public function __construct(
        private readonly RollSpec $spec,
        private readonly array $rolls,
        private readonly array $successes,
        private readonly array $adjustedRolls,
        private readonly array $droppedRolls,
        private readonly int $totalSuccesses,
        private readonly ?array $appliedNames,
    ) {
    }

    /**
     * Спека после дефолтов.
     *
     * @return RollSpec Спека.
     */
    public function getSpec(): RollSpec
    {
        return $this->spec;
    }

    /**
     * Все брошенные грани.
     *
     * @return array<int, int> Грани.
     */
    public function getRolls(): array
    {
        return $this->rolls;
    }

    /**
     * Успехи по оставшимся граням.
     *
     * @return array<int, int> Успехи.
     */
    public function getSuccesses(): array
    {
        return $this->successes;
    }

    /**
     * Грани после сброса.
     *
     * @return array<int, int> Грани.
     */
    public function getAdjustedRolls(): array
    {
        return $this->adjustedRolls;
    }

    /**
     * Сброшенные грани.
     *
     * @return array<int, int> Грани.
     */
    public function getDroppedRolls(): array
    {
        return $this->droppedRolls;
    }

    /**
     * Сумма успехов.
     *
     * @return int Сумма.
     */
    public function getTotalSuccesses(): int
    {
        return $this->totalSuccesses;
    }

    /**
     * Имена сработавших механик.
     *
     * @return array<int, string>|null Имена или null.
     */
    public function getAppliedNames(): ?array
    {
        return $this->appliedNames;
    }
}
