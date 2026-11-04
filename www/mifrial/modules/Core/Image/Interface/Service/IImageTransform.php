<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Interface\Service;

use Mifrial\Core\Image\Dto\ImageResult;
use Mifrial\Core\Image\Dto\ImageTransformSpec;

/**
 * Порт построения производной картинки.
 */
interface IImageTransform
{
    /**
     * Строит производную и кладёт её во временный файл.
     *
     * Файл результата удаляет вызывающий; модуль за ним не следит.
     *
     * @param string $sourcePath Абсолютный путь к исходнику.
     * @param ImageTransformSpec $spec Параметры производной.
     *
     * @return ImageResult Временный файл и его свойства.
     */
    public function transform(string $sourcePath, ImageTransformSpec $spec): ImageResult;
}
