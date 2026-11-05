<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Dto;

use Mifrial\Core\Image\Exception\ImageSpecInvalidException;

/**
 * Прямоугольник кропа в пикселях исходника до вписывания в рамку.
 */
final class ImageCrop
{
    private const MAX_SIDE = 20000;

    /**
     * Собирает прямоугольник кропа.
     *
     * @param int $x Левый край, ≥ 0.
     * @param int $y Верхний край, ≥ 0.
     * @param int $width Ширина, 1..20000.
     * @param int $height Высота, 1..20000.
     *
     * @return void
     *
     * @throws ImageSpecInvalidException Если параметр вне диапазона.
     */
    public function __construct(
        private readonly int $x,
        private readonly int $y,
        private readonly int $width,
        private readonly int $height,
    ) {
        if ($x < 0 || $y < 0) {
            throw new ImageSpecInvalidException('Image crop offset must be non-negative');
        }

        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_SIDE
            || $height > self::MAX_SIDE
        ) {
            throw new ImageSpecInvalidException('Image crop side must be 1..' . self::MAX_SIDE);
        }
    }

    /**
     * Левый край.
     *
     * @return int Пиксели.
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * Верхний край.
     *
     * @return int Пиксели.
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * Ширина.
     *
     * @return int Пиксели.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Высота.
     *
     * @return int Пиксели.
     */
    public function getHeight(): int
    {
        return $this->height;
    }
}
