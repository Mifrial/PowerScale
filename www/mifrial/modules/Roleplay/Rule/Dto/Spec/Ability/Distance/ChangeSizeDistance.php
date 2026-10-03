<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

/**
 * Ветка change_size.
 */
final class ChangeSizeDistance implements ProcessDistance
{
    /**
     * Создаёт ветку.
     *
     * @param ProcessDistance $distance Вложенная дистанция.
     * @param int $sizeDelta Сдвиг размера.
     *
     * @return void
     */
    public function __construct(
        private readonly ProcessDistance $distance,
        private readonly int $sizeDelta,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'change_size';
    }

    /**
     * Вложенная дистанция.
     *
     * @return ProcessDistance Значение.
     */
    public function getDistance(): ProcessDistance
    {
        return $this->distance;
    }

    /**
     * Сдвиг размера.
     *
     * @return int Значение.
     */
    public function getSizeDelta(): int
    {
        return $this->sizeDelta;
    }
}
