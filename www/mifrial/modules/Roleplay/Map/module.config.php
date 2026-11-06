<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Map\Action\ArchiveMapAction;
use Mifrial\Roleplay\Map\Action\FindMapPageAction;
use Mifrial\Roleplay\Map\Action\GetMapAction;
use Mifrial\Roleplay\Map\Action\RouteMapAction;
use Mifrial\Roleplay\Map\Action\SaveMapAction;
use Mifrial\Roleplay\Map\Container\MapContainer;
use Mifrial\Roleplay\Map\Interface\Container\IMapContainer;
use Mifrial\Roleplay\Map\Service\MapCatalogHttp;
use Mifrial\Roleplay\Map\Service\MapPortFactory;
use Mifrial\Roleplay\Map\Service\MapRouteHttp;
use Mifrial\Roleplay\Map\Service\MapSaveHttp;
use Mifrial\Roleplay\Map\Setup\MapModuleSetup;

return [
    'container' => MapContainer::class,
    'locator' => IMapContainer::class,
    'setup' => MapModuleSetup::class,
    'ports' => [
        MapCatalogHttp::class => static function (IServiceLocator $serviceLocator): MapCatalogHttp {
            return (new MapPortFactory())->createCatalogHttp($serviceLocator);
        },
        MapSaveHttp::class => static function (IServiceLocator $serviceLocator): MapSaveHttp {
            return (new MapPortFactory())->createSaveHttp($serviceLocator);
        },
        MapRouteHttp::class => static function (IServiceLocator $serviceLocator): MapRouteHttp {
            return (new MapPortFactory())->createRouteHttp($serviceLocator);
        },
        FindMapPageAction::class => static function (IServiceLocator $serviceLocator): FindMapPageAction {
            return new FindMapPageAction((new MapPortFactory())->fromCatalogContainer($serviceLocator));
        },
        GetMapAction::class => static function (IServiceLocator $serviceLocator): GetMapAction {
            return new GetMapAction((new MapPortFactory())->fromCatalogContainer($serviceLocator));
        },
        ArchiveMapAction::class => static function (IServiceLocator $serviceLocator): ArchiveMapAction {
            return new ArchiveMapAction((new MapPortFactory())->fromCatalogContainer($serviceLocator));
        },
        SaveMapAction::class => static function (IServiceLocator $serviceLocator): SaveMapAction {
            return new SaveMapAction((new MapPortFactory())->fromSaveContainer($serviceLocator));
        },
        RouteMapAction::class => static function (IServiceLocator $serviceLocator): RouteMapAction {
            return new RouteMapAction((new MapPortFactory())->fromRouteContainer($serviceLocator));
        },
    ],
    'routes' => [
        'map.findPage' => ['handler' => FindMapPageAction::class, 'csrf' => true],
        'map.get' => ['handler' => GetMapAction::class, 'csrf' => true],
        'map.save' => ['handler' => SaveMapAction::class, 'csrf' => true],
        'map.archive' => ['handler' => ArchiveMapAction::class, 'csrf' => true],
        'map.route' => ['handler' => RouteMapAction::class, 'csrf' => true],
    ],
    'events' => [],
];
