<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Размерное число: целая база, размер и необязательная шкала модификации.
 */
class DimensionalNumber
{
    /**
     * Создаёт число.
     *
     * @param int $base База.
     * @param int $size Размер.
     * @param int|null $baseMin Нижняя граница базы или null.
     * @param int|null $baseMax Верхняя граница базы или null.
     *
     * @return void
     *
     * @throws RuleInvalidException Если задана неполная или пустая шкала.
     */
    public function __construct(
        private readonly int $base,
        private readonly int $size,
        private readonly ?int $baseMin = null,
        private readonly ?int $baseMax = null,
    ) {
        if (
            ($baseMin === null) !== ($baseMax === null)
            || ($baseMin !== null && $baseMax !== null && $baseMin >= $baseMax)
        ) {
            throw new RuleInvalidException('Шкала размерного числа невалидна');
        }
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
     * Сравнивает с другим числом: оба к меньшему размеру, затем базы.
     *
     * @param self $other Второе число.
     *
     * @return int −1, 0 или 1.
     */
    public function compare(self $other): int
    {
        $size = min($this->size, $other->size);

        return $this->baseAt($size) <=> $other->baseAt($size);
    }

    /**
     * База, приведённая к размеру не больше собственного.
     *
     * @param int $size Целевой размер.
     *
     * @return int База.
     */
    private function baseAt(int $size): int
    {
        $base = $this->base;
        $steps = $this->size - $size;
        for ($step = 0; $step < $steps; $step++) {
            $base *= 2;
        }

        return $base;
    }

    /**
     * Складывает с другим числом к меньшему размеру. Только числа без шкалы.
     *
     * @param self $other Второе число.
     *
     * @return static Сумма.
     *
     * @throws RuleInvalidException Если у числа есть шкала базы.
     */
    public function add(self $other): static
    {
        if (
            $this->baseMin !== null || $this->baseMax !== null
            || $other->baseMin !== null || $other->baseMax !== null
        ) {
            throw new RuleInvalidException('Размерное число со шкалой нельзя складывать');
        }

        $size = min($this->size, $other->size);

        return $this->copyWithBaseSize(
            $this->baseAt($size) + $other->baseAt($size),
            $size,
        );
    }

    /**
     * Вычитает другое число к меньшему размеру. Только числа без шкалы.
     *
     * @param self $other Вычитаемое.
     *
     * @return static Разность.
     *
     * @throws RuleInvalidException Если у числа есть шкала базы.
     */
    public function subtract(self $other): static
    {
        if (
            $this->baseMin !== null || $this->baseMax !== null
            || $other->baseMin !== null || $other->baseMax !== null
        ) {
            throw new RuleInvalidException('Размерное число со шкалой нельзя вычитать');
        }

        $size = min($this->size, $other->size);

        return $this->copyWithBaseSize(
            $this->baseAt($size) - $other->baseAt($size),
            $size,
        );
    }

    /**
     * Задаёт размер, сохраняя базу: значение читается в новой шкале.
     *
     * @param int $targetSize Целевой размер.
     *
     * @return static Копия с новым размером.
     */
    public function withSize(int $targetSize): static
    {
        return $this->copyWithBaseSize($this->base, $targetSize);
    }

    /**
     * Умножает на другое размерное число: базы перемножаются, размеры складываются.
     *
     * @param self $other Второй множитель.
     *
     * @return static Произведение.
     *
     * @throws RuleInvalidException Если произведение не помещается в int.
     */
    public function multiply(self $other): static
    {
        if ($this->base !== 0 && abs($other->base) > intdiv(PHP_INT_MAX, abs($this->base))) {
            throw new RuleInvalidException('Произведение размерных чисел не помещается в int');
        }

        return $this->copyWithBaseSize(
            $this->base * $other->base,
            $this->size + $other->size,
        );
    }

    /**
     * Модифицирует базу по собственной шкале. Шаг размера — (max − min + 1).
     *
     * @param int $delta Пункты.
     *
     * @return static Модифицированная копия того же класса.
     *
     * @throws RuleInvalidException Если у числа нет шкалы.
     */
    public function modify(int $delta): static
    {
        if ($this->baseMin === null || $this->baseMax === null) {
            throw new RuleInvalidException('У размерного числа отсутствует шкала');
        }

        $baseMin = $this->baseMin;
        $baseMax = $this->baseMax;
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

        return $this->copyWithBaseSize($base, $size);
    }

    /**
     * Создаёт immutable-копию с новой базой и размером.
     *
     * @param int $base База копии.
     * @param int $size Размер копии.
     *
     * @return static Копия того же фактического класса.
     */
    protected function copyWithBaseSize(int $base, int $size): static
    {
        return new static($base, $size, $this->baseMin, $this->baseMax);
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
