<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Service;

use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Mifrial\Core\Image\Interface\Service\IImageTransform;

/**
 * Сборка сервисов Image; здесь же выбирается драйвер библиотеки.
 */
final class ImagePortFactory
{
    /**
     * Порт преобразования картинок.
     *
     * Драйвер — единственная точка замены GD/Imagick/другой библиотеки.
     * Анимацию не декодируем: многокадровый webp сводится к первому кадру.
     *
     * @return IImageTransform Порт.
     */
    public function create(): IImageTransform
    {
        return new InterventionImageTransform(
            new ImageManager(new ImagickDriver(), decodeAnimation: false),
        );
    }
}
