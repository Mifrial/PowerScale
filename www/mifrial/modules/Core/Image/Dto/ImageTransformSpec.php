<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Dto;

use Mifrial\Core\Image\Enum\FitMode;
use Mifrial\Core\Image\Enum\OutputFormat;
use Mifrial\Core\Image\Exception\ImageSpecInvalidException;

/**
 * Параметры одной производной: рамка, режим вписывания, формат и качество.
 */
final class ImageTransformSpec
{
    private const MAX_SIDE = 20000;

    /**
     * Собирает спецификацию.
     *
     * @param int $width Ширина рамки, 1..20000.
     * @param int $height Высота рамки, 1..20000.
     * @param FitMode $mode Режим вписывания.
     * @param OutputFormat $format Формат результата.
     * @param int $quality Качество 1..100.
     * @param ImageCrop|null $crop Кроп исходника до вписывания или null.
     *
     * @return void
     *
     * @throws ImageSpecInvalidException Если параметр вне диапазона.
     */
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly FitMode $mode,
        private readonly OutputFormat $format,
        private readonly int $quality,
        private readonly ?ImageCrop $crop = null,
    ) {
        if ($width < 1 || $width > self::MAX_SIDE || $height < 1 || $height > self::MAX_SIDE) {
            throw new ImageSpecInvalidException('Image frame side must be 1..' . self::MAX_SIDE);
        }

        if ($quality < 1 || $quality > 100) {
            throw new ImageSpecInvalidException('Image quality must be 1..100');
        }
    }

    /**
     * Ширина рамки.
     *
     * @return int Пиксели.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Высота рамки.
     *
     * @return int Пиксели.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Режим вписывания.
     *
     * @return FitMode Режим.
     */
    public function getMode(): FitMode
    {
        return $this->mode;
    }

    /**
     * Формат результата.
     *
     * @return OutputFormat Формат.
     */
    public function getFormat(): OutputFormat
    {
        return $this->format;
    }

    /**
     * Качество кодирования.
     *
     * @return int 1..100.
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * Кроп исходника до вписывания.
     *
     * @return ImageCrop|null Прямоугольник или null.
     */
    public function getCrop(): ?ImageCrop
    {
        return $this->crop;
    }
}
