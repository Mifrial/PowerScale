<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Закрытие процесса карточкой-потомком: порог счётчиков и пакет удара.
 */
final class ProcessCloseSpec
{
    /**
     * Создаёт описание закрытия.
     *
     * @param array<int, CounterThreshold> $requiresCounters Пороги; пустой список — без порога.
     * @param StrikeModifier|null $strike Пакет удара или null.
     *
     * @return void
     */
    public function __construct(
        private readonly array $requiresCounters = [],
        private readonly ?StrikeModifier $strike = null,
    ) {
    }

    /**
     * Пороги счётчиков.
     *
     * @return array<int, CounterThreshold> Пороги.
     */
    public function getRequiresCounters(): array
    {
        return $this->requiresCounters;
    }

    /**
     * Пакет удара.
     *
     * @return StrikeModifier|null Пакет или null.
     */
    public function getStrike(): ?StrikeModifier
    {
        return $this->strike;
    }

    /**
     * Выполнены ли пороги закрытия.
     *
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return bool true, если можно закрывать.
     */
    public function meets(array $counters): bool
    {
        return CounterThreshold::areMet($this->requiresCounters, $counters);
    }
}
