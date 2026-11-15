<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Tests;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use Mifrial\Core\Image\Exception\ImageSourceInvalidException;
use Mifrial\Core\Image\Interface\Service\IImageFrame;
use Mifrial\Core\Image\Service\ImagePortFactory;
use PHPUnit\Framework\TestCase;

final class ImagickImageFrameTest extends TestCase
{
    private string $directory = '';

    /**
     * Готовит каталог фикстур.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mifrial-frame-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0770, true);
    }

    /**
     * Сносит фикстуры.
     *
     * @return void
     */
    protected function tearDown(): void
    {
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
     * Картинка без альфы считается целой: белый фон — не прозрачное поле.
     *
     * @return void
     */
    public function testOpaqueImageKeepsFullFrame(): void
    {
        $frame = $this->frame()->frame($this->pngSource(64, 48, new ImagickPixel('red')));

        self::assertSame(['x' => 0, 'y' => 0, 'width' => 64, 'height' => 48], $frame->toArray());
    }

    /**
     * Прозрачные поля обрезаются до видимой массы.
     *
     * @return void
     */
    public function testTransparentPaddingIsTrimmed(): void
    {
        $path = $this->transparentPng(100, 80, 20, 10, 60, 50);
        $frame = $this->frame()->frame($path);

        self::assertSame(['x' => 20, 'y' => 10, 'width' => 40, 'height' => 40], $frame->toArray());
    }

    /**
     * Полностью прозрачная картинка остаётся целой: якоря у неё нет.
     *
     * @return void
     */
    public function testFullyTransparentImageKeepsFullFrame(): void
    {
        $image = new Imagick();
        $image->newImage(30, 20, new ImagickPixel('transparent'), 'png');
        $path = $this->directory . '/empty.png';
        $image->writeImage($path);
        $image->clear();

        $frame = $this->frame()->frame($path);

        self::assertSame(['x' => 0, 'y' => 0, 'width' => 30, 'height' => 20], $frame->toArray());
    }

    /**
     * Отсутствующий файл отвергается.
     *
     * @return void
     */
    public function testRejectsMissingSource(): void
    {
        $this->expectException(ImageSourceInvalidException::class);
        $this->frame()->frame($this->directory . '/absent.png');
    }

    /**
     * Порт с Imagick.
     *
     * @return IImageFrame Порт.
     */
    private function frame(): IImageFrame
    {
        return (new ImagePortFactory())->createFrame();
    }

    /**
     * Однотонный PNG.
     *
     * @param int $width Ширина.
     * @param int $height Высота.
     * @param ImagickPixel $color Цвет.
     *
     * @return string Путь.
     */
    private function pngSource(int $width, int $height, ImagickPixel $color): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, $color, 'png');
        $path = $this->directory . '/source-' . $width . 'x' . $height . '.png';
        $image->writeImage($path);
        $image->clear();

        return $path;
    }

    /**
     * Прозрачный PNG с прямоугольником.
     *
     * @param int $width Ширина.
     * @param int $height Высота.
     * @param int $x Левый край прямоугольника.
     * @param int $y Верхний край прямоугольника.
     * @param int $right Правый край прямоугольника.
     * @param int $bottom Нижний край прямоугольника.
     *
     * @return string Путь.
     */
    private function transparentPng(
        int $width,
        int $height,
        int $x,
        int $y,
        int $right,
        int $bottom,
    ): string {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('transparent'), 'png');
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('red'));
        $draw->rectangle($x, $y, $right - 1, $bottom - 1);
        $image->drawImage($draw);
        $path = $this->directory . '/visible.png';
        $image->writeImage($path);
        $image->clear();

        return $path;
    }
}
