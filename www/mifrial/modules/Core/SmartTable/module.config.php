<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Container\SmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Interface\Service\ITableCatalog;
use Mifrial\Core\SmartTable\Service\DatabaseConnectionPortFactory;
use Mifrial\Core\SmartTable\Service\HydratorRegistry;
use Mifrial\Core\SmartTable\Service\SmartTableGatewayPortFactory;
use Mifrial\Core\SmartTable\Service\TableCatalogPortFactory;
use Mifrial\Core\SmartTable\Setup\SmartTableModuleSetup;

return [
    'container' => SmartTableContainer::class,
    'locator' => ISmartTableContainer::class,
    'setup' => SmartTableModuleSetup::class,
    'ports' => [
        IDatabaseConnection::class => static function (
            IServiceLocator $serviceLocator,
        ): IDatabaseConnection {
            return (new DatabaseConnectionPortFactory())->create($serviceLocator);
        },
        ISmartTableGateway::class => static function (
            IServiceLocator $serviceLocator,
            IModuleContainer $moduleContainer,
        ): ISmartTableGateway {
            return (new SmartTableGatewayPortFactory())->create($serviceLocator, $moduleContainer);
        },
        ITableCatalog::class => static function (
            IServiceLocator $serviceLocator,
            IModuleContainer $moduleContainer,
        ): ITableCatalog {
            return (new TableCatalogPortFactory())->create($serviceLocator, $moduleContainer);
        },
        HydratorRegistry::class => static fn (): HydratorRegistry => new HydratorRegistry(),
    ],
    // smarttable_hydrators — бронь ключа для модулей-потребителей; сканера в этом плане нет.
    'routes' => [],
    'events' => [],
];
