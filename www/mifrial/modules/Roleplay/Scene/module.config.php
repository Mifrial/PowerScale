<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Scene\Action\ArchiveSceneAction;
use Mifrial\Roleplay\Scene\Action\FindScenePageAction;
use Mifrial\Roleplay\Scene\Action\GetSceneAction;
use Mifrial\Roleplay\Scene\Action\SaveSceneAction;
use Mifrial\Roleplay\Scene\Container\SceneContainer;
use Mifrial\Roleplay\Scene\Interface\Container\ISceneContainer;
use Mifrial\Roleplay\Scene\Service\SceneCatalogHttp;
use Mifrial\Roleplay\Scene\Service\ScenePortFactory;
use Mifrial\Roleplay\Scene\Service\SceneSaveHttp;
use Mifrial\Roleplay\Scene\Setup\SceneModuleSetup;

return [
    'container' => SceneContainer::class,
    'locator' => ISceneContainer::class,
    'setup' => static function (IServiceLocator $serviceLocator): SceneModuleSetup {
        return (new ScenePortFactory())->createSetup($serviceLocator);
    },
    'ports' => [
        SceneCatalogHttp::class => static function (IServiceLocator $serviceLocator): SceneCatalogHttp {
            return (new ScenePortFactory())->createCatalogHttp($serviceLocator);
        },
        SceneSaveHttp::class => static function (IServiceLocator $serviceLocator): SceneSaveHttp {
            return (new ScenePortFactory())->createSaveHttp($serviceLocator);
        },
        FindScenePageAction::class => static function (IServiceLocator $serviceLocator): FindScenePageAction {
            return new FindScenePageAction((new ScenePortFactory())->fromCatalogContainer($serviceLocator));
        },
        GetSceneAction::class => static function (IServiceLocator $serviceLocator): GetSceneAction {
            return new GetSceneAction((new ScenePortFactory())->fromCatalogContainer($serviceLocator));
        },
        ArchiveSceneAction::class => static function (IServiceLocator $serviceLocator): ArchiveSceneAction {
            return new ArchiveSceneAction((new ScenePortFactory())->fromCatalogContainer($serviceLocator));
        },
        SaveSceneAction::class => static function (IServiceLocator $serviceLocator): SaveSceneAction {
            return new SaveSceneAction((new ScenePortFactory())->fromSaveContainer($serviceLocator));
        },
    ],
    'routes' => [
        'scene.findPage' => ['handler' => FindScenePageAction::class, 'csrf' => true],
        'scene.get' => ['handler' => GetSceneAction::class, 'csrf' => true],
        'scene.save' => ['handler' => SaveSceneAction::class, 'csrf' => true],
        'scene.archive' => ['handler' => ArchiveSceneAction::class, 'csrf' => true],
    ],
    'events' => [],
];
