<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Logger\Action\FindLogPageAction;
use Mifrial\Core\Logger\Action\GetLogAction;
use Mifrial\Core\Logger\Container\LoggerContainer;
use Mifrial\Core\Logger\Interface\Container\ILoggerContainer;
use Mifrial\Core\Logger\Service\LoggerHttpService;
use Mifrial\Core\Logger\Service\LoggerPortFactory;
use Mifrial\Core\Logger\Service\TableLogWriter;
use Mifrial\Core\Logger\Setup\LoggerModuleSetup;

return [
    'container' => LoggerContainer::class,
    'locator' => ILoggerContainer::class,
    'setup' => LoggerModuleSetup::class,
    'ports' => [
        TableLogWriter::class => static function (IServiceLocator $serviceLocator): TableLogWriter {
            return (new LoggerPortFactory())->create($serviceLocator);
        },
        LoggerHttpService::class => static function (IServiceLocator $serviceLocator): LoggerHttpService {
            return (new LoggerPortFactory())->createHttp($serviceLocator);
        },
        FindLogPageAction::class => static function (IServiceLocator $serviceLocator): FindLogPageAction {
            return new FindLogPageAction((new LoggerPortFactory())->fromContainer($serviceLocator));
        },
        GetLogAction::class => static function (IServiceLocator $serviceLocator): GetLogAction {
            return new GetLogAction((new LoggerPortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'logger.findPage' => [
            'handler' => FindLogPageAction::class,
            'csrf' => true,
        ],
        'logger.get' => [
            'handler' => GetLogAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
