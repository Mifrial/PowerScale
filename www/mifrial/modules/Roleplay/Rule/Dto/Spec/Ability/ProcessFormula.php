<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Формула процесса: фиксированное число или число от счётчика сессии.
 *
 * Не узел формульной подсистемы: у процесса свои источники и своё округление.
 */
final class ProcessFormula
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_COUNTER = 'counter';

    /**
     * Создаёт формулу.
     *
     * @param string $type fixed или counter.
     * @param int $value Значение fixed.
     * @param string|null $counterCode Счётчик у counter.
     * @param int $factor Множитель счётчика.
     * @param int $divisor Делитель, больше нуля.
     * @param int|null $minValue Нижняя граница.
     * @param int|null $maxValue Верхняя граница.
     *
     * @return void
     */
    private function __construct(
        private readonly string $type,
        private readonly int $value,
        private readonly ?string $counterCode,
        private readonly int $factor,
        private readonly int $divisor,
        private readonly ?int $minValue,
        private readonly ?int $maxValue,
    ) {
    }

    /**
     * Фиксированное число.
     *
     * @param int $value Значение.
     *
     * @return self Формула.
     */
    public static function fixed(int $value): self
    {
        return new self(self::TYPE_FIXED, $value, null, 1, 1, null, null);
    }

    /**
     * Число от счётчика: floor(счётчик × factor / divisor), затем границы.
     *
     * @param string $counterCode Счётчик.
     * @param int $factor Множитель.
     * @param int $divisor Делитель, больше нуля.
     * @param int|null $minValue Нижняя граница.
     * @param int|null $maxValue Верхняя граница.
     *
     * @return self Формула.
     */
    public static function counter(
        string $counterCode,
        int $factor = 1,
        int $divisor = 1,
        ?int $minValue = null,
        ?int $maxValue = null,
    ): self {
        return new self(self::TYPE_COUNTER, 0, $counterCode, $factor, $divisor, $minValue, $maxValue);
    }

    /**
     * Значение по счётчикам сессии. Неизвестный счётчик — 0.
     *
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return int Значение.
     */
    public function evaluate(array $counters): int
    {
        if ($this->type === self::TYPE_FIXED) {
            return $this->clamped($this->value);
        }

        $raw = $this->counterCode === null ? 0 : ($counters[$this->counterCode] ?? 0);
        $counter = is_int($raw) ? $raw : 0;
        $product = $counter * $this->factor;

        return $this->clamped(self::floorDiv($product, max(1, $this->divisor)));
    }

    /**
     * Тип формулы.
     *
     * @return string fixed или counter.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Счётчик формулы.
     *
     * @return string|null Код или null у fixed.
     */
    public function getCounterCode(): ?string
    {
        return $this->counterCode;
    }

    /**
     * Множитель.
     *
     * @return int Число.
     */
    public function getFactor(): int
    {
        return $this->factor;
    }

    /**
     * Делитель.
     *
     * @return int Число больше нуля.
     */
    public function getDivisor(): int
    {
        return $this->divisor;
    }

    /**
     * Нижняя граница.
     *
     * @return int|null Число или null.
     */
    public function getMinValue(): ?int
    {
        return $this->minValue;
    }

    /**
     * Верхняя граница.
     *
     * @return int|null Число или null.
     */
    public function getMaxValue(): ?int
    {
        return $this->maxValue;
    }

    /**
     * Сначала нижняя граница, затем верхняя.
     *
     * @param int $value Значение.
     *
     * @return int Значение в границах.
     */
    private function clamped(int $value): int
    {
        if ($this->minValue !== null) {
            $value = max($this->minValue, $value);
        }

        if ($this->maxValue !== null) {
            $value = min($this->maxValue, $value);
        }

        return $value;
    }

    /**
     * Деление с округлением вниз, в том числе при отрицательном делимом.
     *
     * @param int $dividend Делимое.
     * @param int $divisor Делитель, больше нуля.
     *
     * @return int Частное.
     */
    private static function floorDiv(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);
        if ($dividend < 0 && $dividend % $divisor !== 0) {
            --$quotient;
        }

        return $quotient;
    }
}
