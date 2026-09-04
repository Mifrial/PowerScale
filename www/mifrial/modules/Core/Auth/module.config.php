<?php

declare(strict_types=1);

use Mifrial\Core\Auth\Action\FinalPasswordResetAction;
use Mifrial\Core\Auth\Action\GetCurrentUserAction;
use Mifrial\Core\Auth\Action\GuestAction;
use Mifrial\Core\Auth\Action\GetPasswordPolicyAction;
use Mifrial\Core\Auth\Action\LoginAction;
use Mifrial\Core\Auth\Action\LogoutAction;
use Mifrial\Core\Auth\Action\RegisterAction;
use Mifrial\Core\Auth\Action\SetPasswordAction;
use Mifrial\Core\Auth\Action\StartPasswordResetAction;
use Mifrial\Core\Auth\Action\UserCreateAction;
use Mifrial\Core\Auth\Container\AuthContainer;
use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Auth\Service\AuthServiceFactory;
use Mifrial\Core\Auth\Service\AuthSessionBinder;
use Mifrial\Core\Auth\Service\PasswordResetService;
use Mifrial\Core\Auth\Service\SetPasswordService;
use Mifrial\Core\Auth\Service\UserCreateService;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

return [
    'container' => AuthContainer::class,
    'locator' => IAuthContainer::class,
    'setup' => static function (IServiceLocator $serviceLocator) {
        return (new AuthServiceFactory())->createSetup($serviceLocator);
    },
    'request_bind' => AuthSessionBinder::class,
    'ports' => [
        AuthService::class => static function (IServiceLocator $serviceLocator): AuthService {
            return (new AuthServiceFactory())->create($serviceLocator);
        },
        UserCreateService::class => static function (IServiceLocator $serviceLocator): UserCreateService {
            return (new AuthServiceFactory())->createUserCreate($serviceLocator);
        },
        AuthSessionBinder::class => static function (IServiceLocator $serviceLocator): AuthSessionBinder {
            return (new AuthServiceFactory())->createSessionBinder($serviceLocator);
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
        GuestAction::class => static function (IServiceLocator $serviceLocator): GuestAction {
            return new GuestAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
        GetPasswordPolicyAction::class => static function (IServiceLocator $serviceLocator): GetPasswordPolicyAction {
            return new GetPasswordPolicyAction((new AuthServiceFactory())->fromContainer($serviceLocator));
        },
        UserCreateAction::class => static function (IServiceLocator $serviceLocator): UserCreateAction {
            return new UserCreateAction((new AuthServiceFactory())->userCreateFromContainer($serviceLocator));
        },
        PasswordResetService::class => static function (IServiceLocator $serviceLocator): PasswordResetService {
            return (new AuthServiceFactory())->createPasswordReset($serviceLocator);
        },
        StartPasswordResetAction::class => static function (IServiceLocator $serviceLocator): StartPasswordResetAction {
            return new StartPasswordResetAction((new AuthServiceFactory())->passwordResetFromContainer($serviceLocator));
        },
        FinalPasswordResetAction::class => static function (IServiceLocator $serviceLocator): FinalPasswordResetAction {
            return new FinalPasswordResetAction((new AuthServiceFactory())->passwordResetFromContainer($serviceLocator));
        },
        SetPasswordService::class => static function (IServiceLocator $serviceLocator): SetPasswordService {
            return (new AuthServiceFactory())->createSetPassword($serviceLocator);
        },
        SetPasswordAction::class => static function (IServiceLocator $serviceLocator): SetPasswordAction {
            return new SetPasswordAction((new AuthServiceFactory())->setPasswordFromContainer($serviceLocator));
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
        'auth.guest' => [
            'handler' => GuestAction::class,
            'csrf' => false,
        ],
        'auth.getPasswordPolicy' => [
            'handler' => GetPasswordPolicyAction::class,
            'csrf' => false,
        ],
        'user.create' => [
            'handler' => UserCreateAction::class,
            'csrf' => true,
        ],
        'auth.startPasswordReset' => [
            'handler' => StartPasswordResetAction::class,
            'csrf' => false,
        ],
        'auth.finalPasswordReset' => [
            'handler' => FinalPasswordResetAction::class,
            'csrf' => false,
        ],
        'auth.setPassword' => [
            'handler' => SetPasswordAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
