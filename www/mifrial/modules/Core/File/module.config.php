<?php

declare(strict_types=1);

use Mifrial\Core\File\Action\FindFilePageAction;
use Mifrial\Core\File\Action\FindOwnImagesAction;
use Mifrial\Core\File\Action\GetFileAction;
use Mifrial\Core\File\Container\FileContainer;
use Mifrial\Core\File\Interface\Container\IFileContainer;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\File\Service\FileHttpService;
use Mifrial\Core\File\Service\FilePortFactory;
use Mifrial\Core\File\Setup\FileModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

return [
    'container' => FileContainer::class,
    'locator' => IFileContainer::class,
    'setup' => FileModuleSetup::class,
    'ports' => [
        IFiles::class => static function (IServiceLocator $serviceLocator): IFiles {
            return (new FilePortFactory())->create($serviceLocator);
        },
        FileHttpService::class => static function (IServiceLocator $serviceLocator): FileHttpService {
            return (new FilePortFactory())->createHttp($serviceLocator);
        },
        FindFilePageAction::class => static function (IServiceLocator $serviceLocator): FindFilePageAction {
            return new FindFilePageAction((new FilePortFactory())->fromContainer($serviceLocator));
        },
        FindOwnImagesAction::class => static function (IServiceLocator $serviceLocator): FindOwnImagesAction {
            return new FindOwnImagesAction((new FilePortFactory())->fromContainer($serviceLocator));
        },
        GetFileAction::class => static function (IServiceLocator $serviceLocator): GetFileAction {
            return new GetFileAction((new FilePortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'file.findPage' => [
            'handler' => FindFilePageAction::class,
            'csrf' => true,
        ],
        'file.findOwnImages' => [
            'handler' => FindOwnImagesAction::class,
            'csrf' => true,
        ],
        'file.get' => [
            'handler' => GetFileAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
