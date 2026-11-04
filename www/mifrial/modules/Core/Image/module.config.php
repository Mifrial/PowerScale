<?php

declare(strict_types=1);

use Mifrial\Core\Image\Container\ImageContainer;
use Mifrial\Core\Image\Interface\Container\IImageContainer;
use Mifrial\Core\Image\Interface\Service\IImageTransform;
use Mifrial\Core\Image\Service\ImagePortFactory;

return [
    'container' => ImageContainer::class,
    'locator' => IImageContainer::class,
    'ports' => [
        IImageTransform::class => static function (): IImageTransform {
            return (new ImagePortFactory())->create();
        },
    ],
    'routes' => [],
    'events' => [],
];
