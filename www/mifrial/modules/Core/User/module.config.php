<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\User\Action\CreateGroupAction;
use Mifrial\Core\User\Action\DeactivateGroupAction;
use Mifrial\Core\User\Action\DeactivateUserAction;
use Mifrial\Core\User\Action\FindGroupPageAction;
use Mifrial\Core\User\Action\FindUserPageAction;
use Mifrial\Core\User\Action\GetGroupAction;
use Mifrial\Core\User\Action\GetGroupMembersAction;
use Mifrial\Core\User\Action\GetUserAction;
use Mifrial\Core\User\Action\GetUsersByIdsAction;
use Mifrial\Core\User\Action\UpdateGroupAction;
use Mifrial\Core\User\Action\UpdateUserAction;
use Mifrial\Core\User\Container\UserContainer;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Interface\Service\IUserViews;
use Mifrial\Core\User\Service\GroupHttpService;
use Mifrial\Core\User\Service\GroupHttpServiceFactory;
use Mifrial\Core\User\Service\UserAccountsPortFactory;
use Mifrial\Core\User\Service\UserGroupsPortFactory;
use Mifrial\Core\User\Service\UserHttpService;
use Mifrial\Core\User\Service\UserHttpServiceFactory;
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
        IUserViews::class => static function (
            IServiceLocator $serviceLocator,
        ): IUserViews {
            return (new UserHttpServiceFactory())->createViews($serviceLocator);
        },
        IUserAccess::class => static function (
            IServiceLocator $serviceLocator,
        ): IUserAccess {
            return (new UserHttpServiceFactory())->createAccess($serviceLocator);
        },
        UserHttpService::class => static function (
            IServiceLocator $serviceLocator,
        ): UserHttpService {
            return (new UserHttpServiceFactory())->create($serviceLocator);
        },
        FindUserPageAction::class => static function (IServiceLocator $serviceLocator): FindUserPageAction {
            return new FindUserPageAction((new UserHttpServiceFactory())->fromContainer($serviceLocator));
        },
        GetUserAction::class => static function (IServiceLocator $serviceLocator): GetUserAction {
            return new GetUserAction((new UserHttpServiceFactory())->fromContainer($serviceLocator));
        },
        GetUsersByIdsAction::class => static function (IServiceLocator $serviceLocator): GetUsersByIdsAction {
            return new GetUsersByIdsAction((new UserHttpServiceFactory())->fromContainer($serviceLocator));
        },
        UpdateUserAction::class => static function (IServiceLocator $serviceLocator): UpdateUserAction {
            return new UpdateUserAction((new UserHttpServiceFactory())->fromContainer($serviceLocator));
        },
        DeactivateUserAction::class => static function (IServiceLocator $serviceLocator): DeactivateUserAction {
            return new DeactivateUserAction((new UserHttpServiceFactory())->fromContainer($serviceLocator));
        },
        GroupHttpService::class => static function (
            IServiceLocator $serviceLocator,
        ): GroupHttpService {
            return (new GroupHttpServiceFactory())->create($serviceLocator);
        },
        FindGroupPageAction::class => static function (IServiceLocator $serviceLocator): FindGroupPageAction {
            return new FindGroupPageAction((new GroupHttpServiceFactory())->fromContainer($serviceLocator));
        },
        GetGroupAction::class => static function (IServiceLocator $serviceLocator): GetGroupAction {
            return new GetGroupAction((new GroupHttpServiceFactory())->fromContainer($serviceLocator));
        },
        GetGroupMembersAction::class => static function (IServiceLocator $serviceLocator): GetGroupMembersAction {
            return new GetGroupMembersAction((new GroupHttpServiceFactory())->fromContainer($serviceLocator));
        },
        CreateGroupAction::class => static function (IServiceLocator $serviceLocator): CreateGroupAction {
            return new CreateGroupAction((new GroupHttpServiceFactory())->fromContainer($serviceLocator));
        },
        UpdateGroupAction::class => static function (IServiceLocator $serviceLocator): UpdateGroupAction {
            return new UpdateGroupAction((new GroupHttpServiceFactory())->fromContainer($serviceLocator));
        },
        DeactivateGroupAction::class => static function (IServiceLocator $serviceLocator): DeactivateGroupAction {
            return new DeactivateGroupAction((new GroupHttpServiceFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'user.findPage' => [
            'handler' => FindUserPageAction::class,
            'csrf' => true,
        ],
        'user.get' => [
            'handler' => GetUserAction::class,
            'csrf' => true,
        ],
        'user.getByIds' => [
            'handler' => GetUsersByIdsAction::class,
            'csrf' => true,
        ],
        'user.update' => [
            'handler' => UpdateUserAction::class,
            'csrf' => true,
        ],
        'user.deactivate' => [
            'handler' => DeactivateUserAction::class,
            'csrf' => true,
        ],
        'userGroup.findPage' => [
            'handler' => FindGroupPageAction::class,
            'csrf' => true,
        ],
        'userGroup.get' => [
            'handler' => GetGroupAction::class,
            'csrf' => true,
        ],
        'userGroup.getMembers' => [
            'handler' => GetGroupMembersAction::class,
            'csrf' => true,
        ],
        'userGroup.create' => [
            'handler' => CreateGroupAction::class,
            'csrf' => true,
        ],
        'userGroup.update' => [
            'handler' => UpdateGroupAction::class,
            'csrf' => true,
        ],
        'userGroup.deactivate' => [
            'handler' => DeactivateGroupAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
