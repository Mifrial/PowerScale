<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Tests;

use Mifrial\Core\Image\Dto\ImageTransformSpec;
use Mifrial\Core\Image\Enum\FitMode;
use Mifrial\Core\Image\Enum\OutputFormat;
use Mifrial\Core\Image\Exception\ImageSpecInvalidException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageTransformSpecTest extends TestCase
{
    /**
     * Параметры вне диапазона отвергаются.
     *
     * @param int $width Ширина.
     * @param int $height Высота.
     * @param int $quality Качество.
     *
     * @return void
     */
    #[DataProvider('invalidSpecProvider')]
    public function testRejectsInvalidSpec(int $width, int $height, int $quality): void
    {
        $this->expectException(ImageSpecInvalidException::class);
        new ImageTransformSpec($width, $height, FitMode::Cover, OutputFormat::Webp, $quality);
    }

    /**
     * Недопустимые значения.
     *
     * @return array<string, array{0: int, 1: int, 2: int}> Наборы.
     */
    public static function invalidSpecProvider(): array
    {
        return [
            'zero width' => [0, 64, 90],
            'zero height' => [64, 0, 90],
            'too wide' => [20001, 64, 90],
            'too tall' => [64, 20001, 90],
            'quality zero' => [64, 64, 0],
            'quality above' => [64, 64, 101],
        ];
    }

    /**
     * Границы диапазонов принимаются.
     *
     * @return void
     */
    public function testAcceptsBoundaryValues(): void
    {
        $spec = new ImageTransformSpec(1, 20000, FitMode::Contain, OutputFormat::Png, 1);

        self::assertSame(1, $spec->getWidth());
        self::assertSame(20000, $spec->getHeight());
        self::assertSame(FitMode::Contain, $spec->getMode());
        self::assertSame(OutputFormat::Png, $spec->getFormat());
        self::assertSame(1, $spec->getQuality());
        self::assertSame('image/png', $spec->getFormat()->mime());
    }
}
