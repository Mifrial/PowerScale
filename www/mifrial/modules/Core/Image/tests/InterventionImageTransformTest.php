<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Tests;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use Mifrial\Core\Image\Dto\ImageCrop;
use Mifrial\Core\Image\Dto\ImageResult;
use Mifrial\Core\Image\Dto\ImageTransformSpec;
use Mifrial\Core\Image\Enum\FitMode;
use Mifrial\Core\Image\Enum\OutputFormat;
use Mifrial\Core\Image\Exception\ImageSourceInvalidException;
use Mifrial\Core\Image\Exception\ImageSpecInvalidException;
use Mifrial\Core\Image\Interface\Service\IImageTransform;
use Mifrial\Core\Image\Service\ImagePortFactory;
use PHPUnit\Framework\TestCase;

final class InterventionImageTransformTest extends TestCase
{
    private string $directory = '';

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    /**
     * Готовит каталог фикстур.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mifrial-image-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0770, true);
    }

    /**
     * Сносит фикстуры и временные результаты.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        if (!is_dir($this->directory)) {
            return;
        }

        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            unlink($this->directory . '/' . $entry);
        }

        rmdir($this->directory);
    }

    /**
     * Cover даёт квадрат, пропорции исходника не растягиваются.
     *
     * @return void
     */
    public function testCoverProducesSquareWebp(): void
    {
        $source = $this->pngSource(64, 48);
        $result = $this->transform()->transform(
            $source,
            new ImageTransformSpec(64, 64, FitMode::Cover, OutputFormat::Webp, 90),
        );

        $this->track($result);

        self::assertSame('image/webp', $result->getMime());
        self::assertSame(64, $result->getWidth());
        self::assertSame(64, $result->getHeight());
        self::assertSame([64, 64, IMAGETYPE_WEBP], $this->encodedShape($result));
        self::assertGreaterThan(0, $result->getSize());
    }

    /**
     * Contain вписывает без кропа: рамка заполняется фоном.
     *
     * @return void
     */
    public function testContainPadsToFrame(): void
    {
        $source = $this->pngSource(64, 48);
        $result = $this->transform()->transform(
            $source,
            new ImageTransformSpec(64, 64, FitMode::Contain, OutputFormat::Jpeg, 80),
        );

        $this->track($result);

        self::assertSame('image/jpeg', $result->getMime());
        self::assertSame(64, $result->getWidth());
        self::assertSame(64, $result->getHeight());
        self::assertSame([64, 64, IMAGETYPE_JPEG], $this->encodedShape($result));
        $this->assertPixelNear($result, 32, 0, 255, 255, 255);
        $this->assertPixelNear($result, 32, 32, 255, 0, 0);
    }

    /**
     * EXIF-ориентация применяется до вписывания.
     *
     * @return void
     */
    public function testOrientsByExif(): void
    {
        $source = $this->jpegSourceWithOrientation();
        $result = $this->transform()->transform(
            $source,
            new ImageTransformSpec(64, 64, FitMode::Contain, OutputFormat::Png, 90),
        );

        $this->track($result);

        self::assertSame(64, $result->getWidth());
        self::assertSame(64, $result->getHeight());
        self::assertSame([64, 64, IMAGETYPE_PNG], $this->encodedShape($result));
        // Исходник 64×48 после поворота стал 48×64: по бокам появился фон.
        $this->assertPixelNear($result, 0, 32, 255, 255, 255);
        $this->assertPixelNear($result, 32, 32, 255, 0, 0);
    }

    /**
     * Битые байты отвергаются как IMAGE_INVALID.
     *
     * @return void
     */
    public function testRejectsBrokenSource(): void
    {
        $path = $this->directory . '/broken.png';
        file_put_contents($path, 'not an image');

        $this->expectException(ImageSourceInvalidException::class);
        $this->transform()->transform(
            $path,
            new ImageTransformSpec(64, 64, FitMode::Cover, OutputFormat::Webp, 90),
        );
    }

    /**
     * Формат вне политики отвергается до драйвера.
     *
     * @return void
     */
    public function testRejectsFormatOutsidePolicy(): void
    {
        $source = $this->gifSource();

        $this->expectException(ImageSourceInvalidException::class);
        $this->transform()->transform(
            $source,
            new ImageTransformSpec(64, 64, FitMode::Cover, OutputFormat::Webp, 90),
        );
    }

    /**
     * Отсутствующий файл отвергается как IMAGE_INVALID.
     *
     * @return void
     */
    public function testRejectsMissingSource(): void
    {
        $this->expectException(ImageSourceInvalidException::class);
        $this->transform()->transform(
            $this->directory . '/absent.png',
            new ImageTransformSpec(64, 64, FitMode::Cover, OutputFormat::Webp, 90),
        );
    }

    /**
     * Создаёт порт с Imagick-драйвером.
     *
     * @return IImageTransform Порт.
     */
    private function transform(): IImageTransform
    {
        return (new ImagePortFactory())->create();
    }

    /**
     * Кроп вырезает прямоугольник до вписывания.
     *
     * @return void
     */
    public function testCropsBeforeFitting(): void
    {
        $source = $this->twoColorPng();
        $result = $this->transform()->transform(
            $source,
            new ImageTransformSpec(
                16,
                16,
                FitMode::Cover,
                OutputFormat::Png,
                90,
                new ImageCrop(32, 0, 32, 32),
            ),
        );

        $this->track($result);

        self::assertSame(16, $result->getWidth());
        self::assertSame(16, $result->getHeight());
        self::assertSame([16, 16, IMAGETYPE_PNG], $this->encodedShape($result));
        $this->assertPixelNear($result, 8, 8, 0, 0, 255);
    }

    /**
     * Кроп за границей исходника отвергается.
     *
     * @return void
     */
    public function testRejectsCropOutsideSource(): void
    {
        $source = $this->pngSource(64, 48);

        $this->expectException(ImageSpecInvalidException::class);
        $this->transform()->transform(
            $source,
            new ImageTransformSpec(
                16,
                16,
                FitMode::Cover,
                OutputFormat::Png,
                90,
                new ImageCrop(48, 0, 32, 32),
            ),
        );
    }

    /**
     * Создаёт PNG заданного размера.
     *
     * @param int $width Ширина.
     * @param int $height Высота.
     *
     * @return string Путь.
     */
    private function pngSource(int $width, int $height): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('red'), 'png');
        $path = $this->directory . '/source-' . $width . 'x' . $height . '.png';
        $image->writeImage($path);
        $image->clear();

        return $path;
    }

    /**
     * Создаёт PNG 64×48: левая половина красная, правая — синяя.
     *
     * @return string Путь.
     */
    private function twoColorPng(): string
    {
        $image = new Imagick();
        $image->newImage(64, 48, new ImagickPixel('red'), 'png');
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('blue'));
        $draw->rectangle(32, 0, 63, 47);
        $image->drawImage($draw);
        $path = $this->directory . '/two-color.png';
        $image->writeImage($path);
        $image->clear();

        return $path;
    }

    /**
     * Создаёт JPEG с EXIF Orientation = 6 (поворот на 90°).
     *
     * @return string Путь.
     */
    private function jpegSourceWithOrientation(): string
    {
        $image = new Imagick();
        $image->newImage(64, 48, new ImagickPixel('red'), 'jpeg');
        $plain = $image->getImageBlob();
        $image->clear();

        $tiff = 'II' . pack('v', 42) . pack('V', 8)
            . pack('v', 1)
            . pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', 6) . pack('v', 0)
            . pack('V', 0);
        $payload = "Exif\0\0" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        $path = $this->directory . '/oriented.jpg';
        file_put_contents($path, substr($plain, 0, 2) . $app1 . substr($plain, 2));

        return $path;
    }

    /**
     * Создаёт GIF — формат вне политики модуля.
     *
     * @return string Путь.
     */
    private function gifSource(): string
    {
        $image = new Imagick();
        $image->newImage(16, 16, new ImagickPixel('blue'), 'gif');
        $path = $this->directory . '/source.gif';
        $image->writeImage($path);
        $image->clear();

        return $path;
    }

    /**
     * Реальные размеры и тип закодированного файла.
     *
     * @param ImageResult $result Результат.
     *
     * @return array{0: int, 1: int, 2: int} Ширина, высота, тип.
     */
    private function encodedShape(ImageResult $result): array
    {
        $imageSize = getimagesize($result->getPath());
        self::assertIsArray($imageSize);

        return [(int) $imageSize[0], (int) $imageSize[1], (int) $imageSize[2]];
    }

    /**
     * Цвет пикселя результата близок к ожидаемому (lossy-форматы).
     *
     * @param ImageResult $result Результат.
     * @param int $x Координата.
     * @param int $y Координата.
     * @param int $red Ожидаемый красный.
     * @param int $green Ожидаемый зелёный.
     * @param int $blue Ожидаемый синий.
     *
     * @return void
     */
    private function assertPixelNear(ImageResult $result, int $x, int $y, int $red, int $green, int $blue): void
    {
        $image = new Imagick($result->getPath());
        $color = $image->getImagePixelColor($x, $y)->getColor();
        $image->clear();

        self::assertEqualsWithDelta($red, $color['r'], 8);
        self::assertEqualsWithDelta($green, $color['g'], 8);
        self::assertEqualsWithDelta($blue, $color['b'], 8);
    }

    /**
     * Запоминает временный файл для уборки.
     *
     * @param ImageResult $result Результат.
     *
     * @return void
     */
    private function track(ImageResult $result): void
    {
        self::assertFileExists($result->getPath());
        $this->temporaryFiles[] = $result->getPath();
    }
}
