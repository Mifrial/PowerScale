<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Целая база и целый размер для сравнения итога проверки.
 */
final class SizedBase
{
    /**
     * Собирает пару.
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
     * Сворачивает отрицательную базу: {base, size} → {0, size + base}.
     *
     * @return self Пара после свёртки.
     */
    public function foldNegative(): self
    {
        if ($this->base >= 0) {
            return $this;
        }

        return new self(0, $this->size + $this->base);
    }

    /**
     * Поднимает размер до минимума. База делится на 2 за каждый шаг.
     *
     * @param int $minSize Нижняя граница размера.
     *
     * @return self Пара не мельче минимума.
     */
    public function raiseToMinSize(int $minSize): self
    {
        $base = $this->base;
        $size = $this->size;
        while ($size < $minSize) {
            $base = intdiv($base, 2);
            $size++;
        }

        return new self($base, $size);
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
}
