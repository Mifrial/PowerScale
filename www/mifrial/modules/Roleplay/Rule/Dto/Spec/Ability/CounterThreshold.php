<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Порог счётчика: код и включительный минимум.
 */
final class CounterThreshold
{
    /**
     * Создаёт порог.
     *
     * @param string $counterCode Счётчик.
     * @param int $min Минимум включительно.
     *
     * @return void
     */
    public function __construct(
        private readonly string $counterCode,
        private readonly int $min,
    ) {
    }

    /**
     * Счётчик.
     *
     * @return string Код.
     */
    public function getCounterCode(): string
    {
        return $this->counterCode;
    }

    /**
     * Минимум.
     *
     * @return int Число.
     */
    public function getMin(): int
    {
        return $this->min;
    }

    /**
     * Выполнен ли порог.
     *
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return bool true, если значение не ниже минимума.
     */
    public function isMet(array $counters): bool
    {
        $raw = $counters[$this->counterCode] ?? 0;

        return (is_int($raw) ? $raw : 0) >= $this->min;
    }

    /**
     * Все пороги сразу: пустой список — выполнен.
     *
     * @param array<int, self> $thresholds Пороги.
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return bool true, если выполнены все.
     */
    public static function areMet(array $thresholds, array $counters): bool
    {
        foreach ($thresholds as $threshold) {
            if (!$threshold->isMet($counters)) {
                return false;
            }
        }

        return true;
    }
}
