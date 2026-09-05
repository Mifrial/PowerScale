<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Mechanic\Action\CreateMechanicAction;
use Mifrial\Roleplay\Mechanic\Action\GetMechanicAction;
use Mifrial\Roleplay\Mechanic\Action\GetMechanicListAction;
use Mifrial\Roleplay\Mechanic\Action\UpdateMechanicAction;
use Mifrial\Roleplay\Mechanic\Container\MechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Service\MechanicHttpService;
use Mifrial\Roleplay\Mechanic\Service\MechanicPortFactory;
use Mifrial\Roleplay\Mechanic\Setup\MechanicModuleSetup;

return [
    'container' => MechanicContainer::class,
    'locator' => IMechanicContainer::class,
    'setup' => MechanicModuleSetup::class,
    'ports' => [
        IMechanics::class => static function (IServiceLocator $serviceLocator): IMechanics {
            return (new MechanicPortFactory())->create($serviceLocator);
        },
        MechanicHttpService::class => static function (IServiceLocator $serviceLocator): MechanicHttpService {
            return (new MechanicPortFactory())->createHttp($serviceLocator);
        },
        GetMechanicListAction::class => static function (IServiceLocator $serviceLocator): GetMechanicListAction {
            return new GetMechanicListAction((new MechanicPortFactory())->fromContainer($serviceLocator));
        },
        GetMechanicAction::class => static function (IServiceLocator $serviceLocator): GetMechanicAction {
            return new GetMechanicAction((new MechanicPortFactory())->fromContainer($serviceLocator));
        },
        CreateMechanicAction::class => static function (IServiceLocator $serviceLocator): CreateMechanicAction {
            return new CreateMechanicAction((new MechanicPortFactory())->fromContainer($serviceLocator));
        },
        UpdateMechanicAction::class => static function (IServiceLocator $serviceLocator): UpdateMechanicAction {
            return new UpdateMechanicAction((new MechanicPortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'mechanic.getList' => [
            'handler' => GetMechanicListAction::class,
            'csrf' => true,
        ],
        'mechanic.get' => [
            'handler' => GetMechanicAction::class,
            'csrf' => true,
        ],
        'mechanic.create' => [
            'handler' => CreateMechanicAction::class,
            'csrf' => true,
        ],
        'mechanic.update' => [
            'handler' => UpdateMechanicAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
