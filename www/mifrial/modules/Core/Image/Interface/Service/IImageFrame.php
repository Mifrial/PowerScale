<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Interface\Service;

use Mifrial\Core\Image\Dto\ImageFrame;
use Mifrial\Core\Image\Exception\ImageSourceInvalidException;
use Mifrial\Core\Image\Exception\ImageTransformException;

/**
 * Порт чтения видимой рамки картинки: где кончаются прозрачные поля.
 *
 * Рамка нужна потребителям, которые выравнивают картинку по её видимой массе:
 * декор сцены ставит низ валуна или кроны на якорь, а не низ файла.
 */
interface IImageFrame
{
    /**
     * Считает рамку видимых пикселей.
     *
     * У картинки без альфа-канала видимая часть — весь файл. Полностью
     * прозрачная картинка тоже считается целой: иначе у неё не было бы
     * осмысленного якоря.
     *
     * @param string $sourcePath Абсолютный путь к исходнику.
     *
     * @return ImageFrame Рамка в пикселях исходника.
     *
     * @throws ImageSourceInvalidException Если файл не читается.
     * @throws ImageTransformException Если драйвер не смог обрезать.
     */
    public function frame(string $sourcePath): ImageFrame;
}
