<?php

declare(strict_types=1);

use Mifrial\Core\Auth\Action\GetCurrentUserAction;
use Mifrial\Core\Auth\Action\GetPasswordPolicyAction;
use Mifrial\Core\Auth\Action\LoginAction;
use Mifrial\Core\Auth\Action\LogoutAction;
use Mifrial\Core\Auth\Action\RegisterAction;
use Mifrial\Core\Auth\Container\AuthContainer;
use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Auth\Service\AuthServiceFactory;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

return [
    'container' => AuthContainer::class,
    'locator' => IAuthContainer::class,
    'setup' => static function (IServiceLocator $serviceLocator) {
        return (new AuthServiceFactory())->createSetup($serviceLocator);
    },
    'ports' => [
        AuthService::class => static function (IServiceLocator $serviceLocator): AuthService {
            return (new AuthServiceFactory())->create($serviceLocator);
        },
        LoginAction::class => static function (IServiceLocator $serviceLocator): LoginAction {
            return new LoginAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
        RegisterAction::class => static function (IServiceLocator $serviceLocator): RegisterAction {
            return new RegisterAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
        LogoutAction::class => static function (IServiceLocator $serviceLocator): LogoutAction {
            return new LogoutAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
        GetCurrentUserAction::class => static function (IServiceLocator $serviceLocator): GetCurrentUserAction {
            return new GetCurrentUserAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
        GetPasswordPolicyAction::class => static function (IServiceLocator $serviceLocator): GetPasswordPolicyAction {
            return new GetPasswordPolicyAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'auth.login' => [
            'handler' => LoginAction::class,
            'csrf' => false,
        ],
        'auth.register' => [
            'handler' => RegisterAction::class,
            'csrf' => false,
        ],
        'auth.logout' => [
            'handler' => LogoutAction::class,
            'csrf' => true,
        ],
        'auth.getCurrentUser' => [
            'handler' => GetCurrentUserAction::class,
            'csrf' => false,
        ],
        'auth.getPasswordPolicy' => [
            'handler' => GetPasswordPolicyAction::class,
            'csrf' => false,
        ],
    ],
    'events' => [],
];
