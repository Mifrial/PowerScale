<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Service;

use Imagick;
use ImagickException;
use ImagickPixel;
use Mifrial\Core\Image\Dto\ImageFrame;
use Mifrial\Core\Image\Exception\ImageSourceInvalidException;
use Mifrial\Core\Image\Exception\ImageTransformException;
use Mifrial\Core\Image\Interface\Service\IImageFrame;

/**
 * Видимая рамка через Imagick: обрезка прозрачных полей.
 *
 * Imagick обрезает картинку по цвету угла на уровне C, поэтому рамка больших
 * спрайтов считается без попиксельного обхода в PHP. У картинки без
 * альфа-канала обрезка запрещена: иначе белый фон фотографии считался бы
 * полем.
 */
final class ImagickImageFrame implements IImageFrame
{
    private const MAX_SOURCE_SIDE = 20000;

    private const MAX_SOURCE_PIXELS = 40000000;

    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    /**
     * Считает рамку видимых пикселей.
     *
     * @param string $sourcePath Абсолютный путь к исходнику.
     *
     * @return ImageFrame Рамка в пикселях исходника.
     *
     * @throws ImageSourceInvalidException Если файл не читается или недопустим.
     * @throws ImageTransformException Если Imagick не смог обрезать картинку.
     */
    public function frame(string $sourcePath): ImageFrame
    {
        [$width, $height] = $this->sourceShape($sourcePath);

        try {
            $imagick = new Imagick($sourcePath);
        } catch (ImagickException $imagickException) {
            throw new ImageSourceInvalidException('Image source is not readable', $imagickException);
        }

        try {
            return $this->trimmedFrame($imagick, $width, $height);
        } catch (ImagickException $imagickException) {
            throw new ImageTransformException('Image frame failed', $imagickException);
        } finally {
            $imagick->clear();
        }
    }

    /**
     * Обрезает прозрачные поля и переводит страницу Imagick в рамку.
     *
     * @param Imagick $imagick Открытая картинка.
     * @param int $width Ширина исходника.
     * @param int $height Высота исходника.
     *
     * @return ImageFrame Рамка.
     *
     * @throws ImagickException Если обрезка не удалась.
     */
    private function trimmedFrame(Imagick $imagick, int $width, int $height): ImageFrame
    {
        if ($imagick->getImageAlphaChannel() !== true) {
            return ImageFrame::full($width, $height);
        }

        // Imagick не обрезает однопиксельную картинку и бросает warning.
        if ($width < 2 || $height < 2) {
            return ImageFrame::full($width, $height);
        }

        $imagick->setImageBackgroundColor(new ImagickPixel('transparent'));
        $imagick->trimImage(0);
        $page = $imagick->getImagePage();
        $x = $page['x'];
        $y = $page['y'];
        $trimmedWidth = $imagick->getImageWidth();
        $trimmedHeight = $imagick->getImageHeight();

        // Полностью прозрачная картинка обрезается в точку со страницей -1:
        // якоря у неё нет, поэтому оставляем целый файл.
        if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
            return ImageFrame::full($width, $height);
        }

        if ($trimmedWidth < 1 || $trimmedHeight < 1) {
            return ImageFrame::full($width, $height);
        }

        return new ImageFrame(
            $x,
            $y,
            min($trimmedWidth, $width - $x),
            min($trimmedHeight, $height - $y),
        );
    }

    /**
     * Читает размеры и тип исходника.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return array{0: int, 1: int} Ширина и высота.
     *
     * @throws ImageSourceInvalidException Если файла нет, формат недопустим или размеры вне пределов.
     */
    private function sourceShape(string $sourcePath): array
    {
        if (!is_file($sourcePath)) {
            throw new ImageSourceInvalidException('Image source file is missing');
        }

        $imageSize = $this->withoutWarnings(static fn (): mixed => getimagesize($sourcePath));
        if (!is_array($imageSize)) {
            throw new ImageSourceInvalidException('Image source is not readable');
        }

        $width = $imageSize[0] ?? null;
        $height = $imageSize[1] ?? null;
        $imageType = $imageSize[2] ?? null;
        if (!is_int($width) || !is_int($height) || !is_int($imageType)) {
            throw new ImageSourceInvalidException('Image source has no dimensions');
        }

        if (!in_array($imageType, self::ALLOWED_IMAGE_TYPES, true)) {
            throw new ImageSourceInvalidException('Image format is not allowed');
        }

        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_SOURCE_SIDE
            || $height > self::MAX_SOURCE_SIDE
            || $width * $height > self::MAX_SOURCE_PIXELS
        ) {
            throw new ImageSourceInvalidException('Image source dimensions are out of bounds');
        }

        return [$width, $height];
    }

    /**
     * Вызывает функцию без предупреждений PHP.
     *
     * @param callable $callback Вызов.
     *
     * @return mixed Результат.
     */
    private function withoutWarnings(callable $callback): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
