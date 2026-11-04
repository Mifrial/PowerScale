<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Service;

use Intervention\Image\Alignment;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Mifrial\Core\Image\Dto\ImageResult;
use Mifrial\Core\Image\Dto\ImageTransformSpec;
use Mifrial\Core\Image\Enum\FitMode;
use Mifrial\Core\Image\Enum\OutputFormat;
use Mifrial\Core\Image\Exception\ImageException;
use Mifrial\Core\Image\Exception\ImageSourceInvalidException;
use Mifrial\Core\Image\Exception\ImageTransformException;
use Mifrial\Core\Image\Interface\Service\IImageTransform;
use Throwable;

/**
 * Порт Image поверх Intervention Image: политика входа, вписывание, запись tmp.
 */
final class InterventionImageTransform implements IImageTransform
{
    private const MAX_SOURCE_PIXELS = 40000000;

    private const MAX_SOURCE_SIDE = 20000;

    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    /**
     * Создаёт адаптер.
     *
     * @param ImageManager $imageManager Менеджер Intervention с выбранным драйвером.
     *
     * @return void
     */
    public function __construct(private readonly ImageManager $imageManager)
    {
    }

    /**
     * Строит производную во временном файле.
     *
     * @param string $sourcePath Абсолютный путь к исходнику.
     * @param ImageTransformSpec $spec Параметры производной.
     *
     * @return ImageResult Временный файл и его свойства.
     *
     * @throws ImageSourceInvalidException Если формат вне политики или файл не читается.
     * @throws ImageTransformException Если драйвер или запись результата не удались.
     */
    public function transform(string $sourcePath, ImageTransformSpec $spec): ImageResult
    {
        $this->assertSource($sourcePath);
        $targetPath = $this->temporaryPath();

        try {
            return $this->encode($sourcePath, $spec, $targetPath);
        } catch (ImageException $imageException) {
            $this->removeFile($targetPath);

            throw $imageException;
        } catch (Throwable $throwable) {
            $this->removeFile($targetPath);

            throw new ImageTransformException('Image transform failed', $throwable);
        }
    }

    /**
     * Проверяет файл, формат и пределы до вызова драйвера.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return void
     *
     * @throws ImageSourceInvalidException Если файл не читается или недопустим.
     */
    private function assertSource(string $sourcePath): void
    {
        [$width, $height, $imageType] = $this->sourceShape($sourcePath);

        if (!in_array($imageType, self::ALLOWED_IMAGE_TYPES, true)) {
            throw new ImageSourceInvalidException('Image format is not allowed');
        }

        if ($this->exceedsBounds($width, $height)) {
            throw new ImageSourceInvalidException('Image source dimensions are out of bounds');
        }
    }

    /**
     * Читает размеры и тип исходника.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return array{0: int, 1: int, 2: int} Ширина, высота, IMAGETYPE_*.
     *
     * @throws ImageSourceInvalidException Если файла нет или он не картинка.
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

        return [$width, $height, $imageType];
    }

    /**
     * Проверяет пределы сторон и пикселей.
     *
     * @param int $width Ширина.
     * @param int $height Высота.
     *
     * @return bool true, если исходник вне пределов.
     */
    private function exceedsBounds(int $width, int $height): bool
    {
        if ($width < 1 || $height < 1) {
            return true;
        }

        if ($width > self::MAX_SOURCE_SIDE || $height > self::MAX_SOURCE_SIDE) {
            return true;
        }

        return $width * $height > self::MAX_SOURCE_PIXELS;
    }

    /**
     * Читает, вписывает и кодирует картинку.
     *
     * @param string $sourcePath Исходник.
     * @param ImageTransformSpec $spec Параметры.
     * @param string $targetPath Временный файл результата.
     *
     * @return ImageResult Результат.
     *
     * @throws ImageTransformException Если результат не записался или пуст.
     */
    private function encode(string $sourcePath, ImageTransformSpec $spec, string $targetPath): ImageResult
    {
        $image = $this->fit($this->imageManager->decode($sourcePath)->orient(), $spec);
        $image->encode($this->encoder($spec))->save($targetPath);

        $size = filesize($targetPath);
        if (!is_int($size) || $size < 1) {
            throw new ImageTransformException('Encoded image is empty');
        }

        return new ImageResult(
            $targetPath,
            $spec->getFormat()->mime(),
            $image->width(),
            $image->height(),
            $size,
        );
    }

    /**
     * Вписывает картинку в рамку без искажения пропорций.
     *
     * @param ImageInterface $image Картинка после orient().
     * @param ImageTransformSpec $spec Параметры.
     *
     * @return ImageInterface Новая картинка.
     */
    private function fit(ImageInterface $image, ImageTransformSpec $spec): ImageInterface
    {
        return match ($spec->getMode()) {
            FitMode::Cover => $image->cover(
                $spec->getWidth(),
                $spec->getHeight(),
                Alignment::CENTER,
            ),
            FitMode::Contain => $image->contain(
                $spec->getWidth(),
                $spec->getHeight(),
                alignment: Alignment::CENTER,
            ),
        };
    }

    /**
     * Кодировщик под формат результата; метаданные не переносим.
     *
     * @param ImageTransformSpec $spec Параметры.
     *
     * @return EncoderInterface Кодировщик.
     */
    private function encoder(ImageTransformSpec $spec): EncoderInterface
    {
        return match ($spec->getFormat()) {
            OutputFormat::Webp => new WebpEncoder($spec->getQuality(), strip: true),
            OutputFormat::Jpeg => new JpegEncoder($spec->getQuality(), strip: true),
            OutputFormat::Png => new PngEncoder(strip: true),
        };
    }

    /**
     * Создаёт пустой временный файл.
     *
     * @return string Абсолютный путь.
     *
     * @throws ImageTransformException Если файл не создался.
     */
    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mifrial-image-');
        if ($path === false) {
            throw new ImageTransformException('Cannot create a temporary image file');
        }

        return $path;
    }

    /**
     * Удаляет временный файл, если он есть.
     *
     * @param string $path Абсолютный путь.
     *
     * @return void
     */
    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            $this->withoutWarnings(static fn (): bool => unlink($path));
        }
    }

    /**
     * Выполняет операцию без предупреждений PHP.
     *
     * @param callable $operation Операция.
     *
     * @return mixed Результат операции.
     */
    private function withoutWarnings(callable $operation): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
