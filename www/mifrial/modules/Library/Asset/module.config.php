<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Library\Asset\Action\ArchiveAssetAction;
use Mifrial\Library\Asset\Action\CreateAssetAction;
use Mifrial\Library\Asset\Action\FindAssetPageAction;
use Mifrial\Library\Asset\Action\UpdateAssetAction;
use Mifrial\Library\Asset\Container\AssetContainer;
use Mifrial\Library\Asset\Interface\Container\IAssetContainer;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Library\Asset\Service\AssetHttpService;
use Mifrial\Library\Asset\Service\AssetPortFactory;
use Mifrial\Library\Asset\Setup\AssetModuleSetup;

return [
    'container' => AssetContainer::class,
    'locator' => IAssetContainer::class,
    'setup' => AssetModuleSetup::class,
    'ports' => [
        IAssetCollections::class => static function (IServiceLocator $serviceLocator): IAssetCollections {
            return (new AssetPortFactory())->createCollections($serviceLocator);
        },
        AssetHttpService::class => static function (IServiceLocator $serviceLocator): AssetHttpService {
            return (new AssetPortFactory())->createHttp($serviceLocator);
        },
        FindAssetPageAction::class => static function (IServiceLocator $serviceLocator): FindAssetPageAction {
            return new FindAssetPageAction((new AssetPortFactory())->fromContainer($serviceLocator));
        },
        CreateAssetAction::class => static function (IServiceLocator $serviceLocator): CreateAssetAction {
            return new CreateAssetAction((new AssetPortFactory())->fromContainer($serviceLocator));
        },
        UpdateAssetAction::class => static function (IServiceLocator $serviceLocator): UpdateAssetAction {
            return new UpdateAssetAction((new AssetPortFactory())->fromContainer($serviceLocator));
        },
        ArchiveAssetAction::class => static function (IServiceLocator $serviceLocator): ArchiveAssetAction {
            return new ArchiveAssetAction((new AssetPortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'asset.findPage' => ['handler' => FindAssetPageAction::class, 'csrf' => true],
        'asset.create' => ['handler' => CreateAssetAction::class, 'csrf' => true],
        'asset.update' => ['handler' => UpdateAssetAction::class, 'csrf' => true],
        'asset.archive' => ['handler' => ArchiveAssetAction::class, 'csrf' => true],
    ],
    'events' => [],
];
