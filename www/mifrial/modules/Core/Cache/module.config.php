<?php

declare(strict_types=1);

use Mifrial\Core\Cache\Container\CacheContainer;
use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Cache\Service\CachePortFactory;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

return [
    'container' => CacheContainer::class,
    'locator' => ICacheContainer::class,
    'ports' => [
        ICacheStore::class => static function (IServiceLocator $serviceLocator): ICacheStore {
            return (new CachePortFactory())->create($serviceLocator);
        },
    ],
    'routes' => [],
    'events' => [],
];
