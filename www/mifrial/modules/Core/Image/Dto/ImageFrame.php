<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Dto;

use Mifrial\Core\Image\Exception\ImageSpecInvalidException;

/**
 * Видимая рамка картинки в пикселях: где кончаются прозрачные поля.
 */
final class ImageFrame
{
    /**
     * Собирает рамку.
     *
     * @param int $x Отступ слева.
     * @param int $y Отступ сверху.
     * @param int $width Ширина видимой части.
     * @param int $height Высота видимой части.
     *
     * @return void
     *
     * @throws ImageSpecInvalidException Если рамка пуста или вывернута.
     */
    public function __construct(
        private readonly int $x,
        private readonly int $y,
        private readonly int $width,
        private readonly int $height,
    ) {
        if ($x < 0 || $y < 0 || $width < 1 || $height < 1) {
            throw new ImageSpecInvalidException('Image frame is out of bounds');
        }
    }

    /**
     * Рамка на всю картинку: прозрачных полей нет.
     *
     * @param int $width Ширина картинки.
     * @param int $height Высота картинки.
     *
     * @return self Рамка.
     *
     * @throws ImageSpecInvalidException Если сторона не положительна.
     */
    public static function full(int $width, int $height): self
    {
        return new self(0, 0, $width, $height);
    }

    /**
     * Отступ слева.
     *
     * @return int Пиксели.
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * Отступ сверху.
     *
     * @return int Пиксели.
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * Ширина видимой части.
     *
     * @return int Пиксели.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Высота видимой части.
     *
     * @return int Пиксели.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * JSON-вид рамки.
     *
     * @return array{x: int, y: int, width: int, height: int} Поля.
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
