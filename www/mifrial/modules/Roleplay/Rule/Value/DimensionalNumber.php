<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Размерное число: целая база и целый размер.
 */
final class DimensionalNumber
{
    /**
     * Создаёт число.
     *
     * @param int $base База.
     * @param int $size Размер.
     *
     * @return void
     */
    public function __construct(
        private readonly int $base,
        private readonly int $size,
    ) {
    }

    /**
     * База.
     *
     * @return int Целое.
     */
    public function getBase(): int
    {
        return $this->base;
    }

    /**
     * Размер.
     *
     * @return int Целое.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Целое floor(база × 2^размер).
     *
     * @return int Число.
     */
    public function toInteger(): int
    {
        return (int) floor($this->base * (2 ** $this->size));
    }

    /**
     * Сдвигает базу по шкале. Шаг размера — (max − min + 1).
     *
     * @param int $delta Пункты.
     * @param int $baseMin Нижняя база.
     * @param int $baseMax Верхняя база.
     *
     * @return self Пара.
     */
    public function shift(int $delta, int $baseMin, int $baseMax): self
    {
        $step = $baseMax - $baseMin + 1;
        $sizeDelta = (int) floor($delta / $step);
        $baseDelta = $delta - $sizeDelta * $step;
        $base = $this->base + $baseDelta;
        $size = $this->size + $sizeDelta;
        if ($base > $baseMax) {
            $base -= $step;
            $size += 1;
        } elseif ($base < $baseMin) {
            $base += $step;
            $size -= 1;
        }

        return new self($base, $size);
    }

    /**
     * Делит на другую пару. Остаток лежит на меньшем размере.
     *
     * @param self $divisor Делитель.
     *
     * @return DimensionalQuotient Частное и остаток.
     *
     * @throws RuleInvalidException База меньше 0, делитель 0 или сдвиг не влезает в int.
     */
    public function divide(self $divisor): DimensionalQuotient
    {
        if ($this->base < 0 || $divisor->base < 0) {
            throw new RuleInvalidException('База размерного числа меньше 0');
        }

        if ($divisor->base === 0) {
            throw new RuleInvalidException('Деление на нулевое размерное число');
        }

        $commonSize = min($this->size, $divisor->size);
        $dividendBase = $this->shiftBase($this->base, $this->sizeGap($this->size, $commonSize));
        $divisorBase = $this->shiftBase($divisor->base, $this->sizeGap($divisor->size, $commonSize));

        return new DimensionalQuotient(
            intdiv($dividendBase, $divisorBase),
            new self($dividendBase % $divisorBase, $commonSize),
        );
    }

    /**
     * Разность размеров. Оба конца уже упорядочены: размер не меньше общего.
     *
     * @param int $size Размер пары.
     * @param int $commonSize Меньший размер.
     *
     * @return int Неотрицательный сдвиг.
     *
     * @throws RuleInvalidException Разность не помещается в int.
     */
    private function sizeGap(int $size, int $commonSize): int
    {
        if ($commonSize < 0 && $size > PHP_INT_MAX + $commonSize) {
            throw new RuleInvalidException('Сдвиг размерного числа не помещается в int');
        }

        return $size - $commonSize;
    }

    /**
     * Умножает базу на 2^сдвиг, не выходя из int.
     *
     * @param int $base Неотрицательная база.
     * @param int $sizeDelta Неотрицательный сдвиг размера.
     *
     * @return int Сдвинутая база.
     *
     * @throws RuleInvalidException Сдвиг не помещается в int.
     */
    private function shiftBase(int $base, int $sizeDelta): int
    {
        if ($base === 0 || $sizeDelta === 0) {
            return $base;
        }

        if ($sizeDelta > 62) {
            throw new RuleInvalidException('Сдвиг размерного числа не помещается в int');
        }

        $shifted = $base;
        for ($step = 0; $step < $sizeDelta; $step++) {
            if ($shifted > intdiv(PHP_INT_MAX, 2)) {
                throw new RuleInvalidException('Сдвиг размерного числа не помещается в int');
            }

            $shifted *= 2;
        }

        return $shifted;
    }
}
