<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Logger\Container\LoggerContainer;
use Mifrial\Core\Logger\Interface\Container\ILoggerContainer;
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
    ],
    'routes' => [],
    'events' => [],
];
