<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Сумма, разность и пол размерных чисел одного удара. В лист не пишется.
 */
final class GameStrikeAmounts
{
    /**
     * Ноль: база 0, размер 0.
     *
     * @return DimensionalNumber Пара.
     */
    public function zero(): DimensionalNumber
    {
        return new DimensionalNumber(0, 0);
    }

    /**
     * Сумма пар. Пустой список — ноль.
     *
     * @param list<DimensionalNumber> $parts Слагаемые.
     *
     * @return DimensionalNumber Пара.
     */
    public function sum(array $parts): DimensionalNumber
    {
        if ($parts === []) {
            return $this->zero();
        }

        $total = $parts[0];
        foreach (array_slice($parts, 1) as $part) {
            $total = $this->add($total, $part);
        }

        return $total;
    }

    /**
     * max(0, (урон − сопротивление) × рейтинг − смягчение). Нет смягчения — вычитается ноль.
     *
     * @param DimensionalNumber $damage Урон.
     * @param DimensionalNumber $resistance Сопротивление.
     * @param int $success Рейтинг попадания.
     * @param DimensionalNumber|null $soak Смягчение или null.
     *
     * @return DimensionalNumber Пара не ниже нуля.
     */
    public function injury(
        DimensionalNumber $damage,
        DimensionalNumber $resistance,
        int $success,
        ?DimensionalNumber $soak,
    ): DimensionalNumber {
        $product = $this->multiply($this->subtract($damage, $resistance), $success);

        return $this->floorAtZero($this->subtract($product, $soak ?? $this->zero()));
    }

    /**
     * Вычитает penetration из защиты и не допускает отрицательной защиты.
     *
     * @param DimensionalNumber $defense Сумма защиты.
     * @param DimensionalNumber $penetration Проникновение.
     *
     * @return DimensionalNumber Эффективная защита.
     */
    public function effectiveDefense(
        DimensionalNumber $defense,
        DimensionalNumber $penetration,
    ): DimensionalNumber {
        return $this->floorAtZero($this->subtract($defense, $penetration));
    }

    /**
     * Пара для JSON итога.
     *
     * @param DimensionalNumber $number Число.
     *
     * @return array{base: int, size: int} База и размер.
     */
    public function view(DimensionalNumber $number): array
    {
        return [
            'base' => $number->getBase(),
            'size' => $number->getSize(),
        ];
    }

    /**
     * Складывает две пары к меньшему размеру.
     *
     * @param DimensionalNumber $left Левое.
     * @param DimensionalNumber $right Правое.
     *
     * @return DimensionalNumber Сумма.
     */
    private function add(DimensionalNumber $left, DimensionalNumber $right): DimensionalNumber
    {
        $size = min($left->getSize(), $right->getSize());

        return new DimensionalNumber(
            $this->alignedBase($left, $size) + $this->alignedBase($right, $size),
            $size,
        );
    }

    /**
     * Вычитает правую пару из левой к меньшему размеру.
     *
     * @param DimensionalNumber $left Уменьшаемое.
     * @param DimensionalNumber $right Вычитаемое.
     *
     * @return DimensionalNumber Разность.
     */
    private function subtract(DimensionalNumber $left, DimensionalNumber $right): DimensionalNumber
    {
        $size = min($left->getSize(), $right->getSize());

        return new DimensionalNumber(
            $this->alignedBase($left, $size) - $this->alignedBase($right, $size),
            $size,
        );
    }

    /**
     * Умножает базу на целый рейтинг. Размер не меняет.
     *
     * @param DimensionalNumber $number Множимое.
     * @param int $factor Рейтинг.
     *
     * @return DimensionalNumber Произведение.
     */
    private function multiply(DimensionalNumber $number, int $factor): DimensionalNumber
    {
        return new DimensionalNumber($number->getBase() * $factor, $number->getSize());
    }

    /**
     * Отрицательную пару заменяет нулём. Ноль и положительная пара остаются.
     *
     * @param DimensionalNumber $number Разность.
     *
     * @return DimensionalNumber Пара.
     */
    private function floorAtZero(DimensionalNumber $number): DimensionalNumber
    {
        $size = min($number->getSize(), 0);
        if ($this->alignedBase($number, $size) < 0) {
            return $this->zero();
        }

        return $number;
    }

    /**
     * База, приведённая к меньшему или равному размеру целым умножением на степень двойки.
     *
     * @param DimensionalNumber $number Пара.
     * @param int $size Общий размер.
     *
     * @return int База.
     */
    private function alignedBase(DimensionalNumber $number, int $size): int
    {
        $base = $number->getBase();
        $steps = $number->getSize() - $size;
        for ($step = 0; $step < $steps; $step++) {
            $base *= 2;
        }

        return $base;
    }
}
