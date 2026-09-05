<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Versioning\Space\Container\SpaceContainer;
use Mifrial\Versioning\Space\Interface\Container\ISpaceContainer;
use Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog;
use Mifrial\Versioning\Space\Service\SpacePortFactory;

return [
    'container' => SpaceContainer::class,
    'locator' => ISpaceContainer::class,
    'ports' => [
        IVersionedCatalog::class => static function (IServiceLocator $serviceLocator): IVersionedCatalog {
            return (new SpacePortFactory())->create($serviceLocator);
        },
    ],
    'routes' => [],
    'events' => [],
];
