<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests\Fixture;

use Mifrial\Core\Image\Dto\ImageFrame;
use Mifrial\Core\Image\Interface\Service\IImageFrame;

/**
 * Видимая рамка для unit-тестов File: байты фейковые, Imagick не нужен.
 */
final class FakeImageFrame implements IImageFrame
{
    /**
     * Создаёт заглушку.
     *
     * @param ImageFrame|null $frame Рамка; null — вся картинка 1×1.
     *
     * @return void
     */
    public function __construct(
        private readonly ?ImageFrame $frame = null,
    ) {
    }

    /**
     * Рамка без чтения байтов.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return ImageFrame Рамка.
     */
    public function frame(string $sourcePath): ImageFrame
    {
        return $this->frame ?? ImageFrame::full(1, 1);
    }
}
