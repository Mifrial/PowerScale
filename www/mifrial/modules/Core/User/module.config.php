<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\User\Container\UserContainer;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Service\UserAccountsPortFactory;
use Mifrial\Core\User\Service\UserGroupsPortFactory;
use Mifrial\Core\User\Setup\UserModuleSetup;

return [
    'container' => UserContainer::class,
    'locator' => IUserContainer::class,
    'setup' => UserModuleSetup::class,
    'ports' => [
        IUserAccounts::class => static function (
            IServiceLocator $serviceLocator,
        ): IUserAccounts {
            return (new UserAccountsPortFactory())->create($serviceLocator);
        },
        IUserGroups::class => static function (
            IServiceLocator $serviceLocator,
        ): IUserGroups {
            return (new UserGroupsPortFactory())->create($serviceLocator);
        },
    ],
    'routes' => [],
    'events' => [],
];
