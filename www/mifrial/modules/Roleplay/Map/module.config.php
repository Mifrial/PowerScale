<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Map\Action\ArchiveMapAction;
use Mifrial\Roleplay\Map\Action\FindMapPageAction;
use Mifrial\Roleplay\Map\Action\GetMapAction;
use Mifrial\Roleplay\Map\Action\GetMapTerrainsAction;
use Mifrial\Roleplay\Map\Action\RouteMapAction;
use Mifrial\Roleplay\Map\Action\SaveMapAction;
use Mifrial\Roleplay\Map\Container\MapContainer;
use Mifrial\Roleplay\Map\Interface\Container\IMapContainer;
use Mifrial\Roleplay\Map\Service\MapCatalogHttp;
use Mifrial\Roleplay\Map\Service\MapPortFactory;
use Mifrial\Roleplay\Map\Service\MapRouteHttp;
use Mifrial\Roleplay\Map\Service\MapSaveHttp;
use Mifrial\Roleplay\Map\Service\MapTerrainHttp;
use Mifrial\Roleplay\Map\Setup\MapModuleSetup;

return [
    'container' => MapContainer::class,
    'locator' => IMapContainer::class,
    'setup' => static function (IServiceLocator $serviceLocator): MapModuleSetup {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Map setup requires ISmartTableGateway');
        }

        return new MapModuleSetup($gateway);
    },
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
        MapTerrainHttp::class => static function (IServiceLocator $serviceLocator): MapTerrainHttp {
            return (new MapPortFactory())->createTerrainHttp($serviceLocator);
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
        GetMapTerrainsAction::class => static function (IServiceLocator $serviceLocator): GetMapTerrainsAction {
            return new GetMapTerrainsAction((new MapPortFactory())->fromTerrainContainer($serviceLocator));
        },
    ],
    'routes' => [
        'map.findPage' => ['handler' => FindMapPageAction::class, 'csrf' => true],
        'map.get' => ['handler' => GetMapAction::class, 'csrf' => true],
        'map.save' => ['handler' => SaveMapAction::class, 'csrf' => true],
        'map.archive' => ['handler' => ArchiveMapAction::class, 'csrf' => true],
        'map.getTerrains' => ['handler' => GetMapTerrainsAction::class, 'csrf' => true],
        'map.route' => ['handler' => RouteMapAction::class, 'csrf' => true],
    ],
    'events' => [],
];
