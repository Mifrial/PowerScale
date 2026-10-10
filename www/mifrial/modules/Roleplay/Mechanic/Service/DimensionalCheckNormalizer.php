<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Roleplay\Mechanic\Dto\SizedBase;

/**
 * Нормализует размерные стороны проверки перед сравнением.
 */
final class DimensionalCheckNormalizer
{
    /**
     * Нормализует пару проверки к общему меньшему размеру.
     *
     * @param SizedBase $successes Успехи.
     * @param SizedBase $difficulty Трудность.
     *
     * @return array{successes: SizedBase, difficulty: SizedBase} Нормализованные стороны.
     */
    public function compare(SizedBase $successes, SizedBase $difficulty): array
    {
        $normalizedSuccesses = $this->foldNegative($successes);
        $normalizedDifficulty = $this->foldNegative($difficulty);
        $targetSize = min($normalizedSuccesses->getSize(), $normalizedDifficulty->getSize());

        return [
            'successes' => $this->alignSuccesses($normalizedSuccesses, $targetSize),
            'difficulty' => $this->align($normalizedDifficulty, $targetSize),
        ];
    }

    /**
     * Нормализует бросок для нижней границы проверки.
     *
     * @param SizedBase $roll Бросок.
     * @param SizedBase $minimum Минимальное значение.
     *
     * @return SizedBase Нормализованный бросок.
     */
    public function clamp(SizedBase $roll, SizedBase $minimum): SizedBase
    {
        $normalized = $this->foldNegative($roll);
        if ($normalized->getBase() === $minimum->getBase()
            && $normalized->getSize() < $minimum->getSize()
        ) {
            return $minimum;
        }

        return $normalized;
    }

    /**
     * Проверяет, достигнут ли минимум после clamp.
     *
     * @param SizedBase $roll Бросок.
     * @param SizedBase $minimum Минимум.
     *
     * @return bool true, если бросок равен минимуму после clamp.
     */
    public function isMinimum(SizedBase $roll, SizedBase $minimum): bool
    {
        $normalized = $this->foldNegative($roll);

        return $normalized->getBase() === $minimum->getBase()
            && $normalized->getSize() <= $minimum->getSize();
    }

    /**
     * Сворачивает отрицательную базу.
     *
     * @param SizedBase $value Значение.
     *
     * @return SizedBase Значение без отрицательной базы.
     */
    private function foldNegative(SizedBase $value): SizedBase
    {
        if ($value->getBase() >= 0) {
            return $value;
        }

        return new SizedBase(0, $value->getSize() + $value->getBase());
    }

    /**
     * Приводит успехи к меньшему размеру с особым нулём.
     *
     * @param SizedBase $value Успехи.
     * @param int $targetSize Целевой размер.
     *
     * @return SizedBase Приведённые успехи.
     */
    private function alignSuccesses(SizedBase $value, int $targetSize): SizedBase
    {
        if ($value->getBase() === 0 && $value->getSize() > $targetSize) {
            $value = new SizedBase(1, $value->getSize() - 1);
        }

        return $this->align($value, $targetSize);
    }

    /**
     * Приводит сторону к размеру.
     *
     * @param SizedBase $value Сторона.
     * @param int $targetSize Целевой размер.
     *
     * @return SizedBase Приведённая сторона.
     */
    private function align(SizedBase $value, int $targetSize): SizedBase
    {
        if ($value->getSize() === $targetSize) {
            return $value;
        }

        return new SizedBase(
            $value->getBase() * (2 ** ($value->getSize() - $targetSize)),
            $targetSize,
        );
    }
}
