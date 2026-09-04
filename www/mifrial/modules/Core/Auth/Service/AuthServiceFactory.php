<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\GroupSecurityPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordResetRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Setup\AuthModuleSetup;
use Mifrial\Core\Auth\Table\AuthGroupSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthPasswordResetTable;
use Mifrial\Core\Auth\Table\AuthSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Mail\Interface\Container\IMailContainer;
use Mifrial\Core\Mail\Interface\Service\IMail;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Interface\Service\IUserViews;

/**
 * Сборка AuthService из локатора.
 */
final class AuthServiceFactory
{
    /**
     * Создаёт сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return AuthService Сценарий.
     *
     * @throws KernelException Если порт соседа неверного типа.
     */
    public function create(IServiceLocator $serviceLocator): AuthService
    {
        $ports = $this->neighborPorts($serviceLocator);
        $identityRepository = new UserIdentityRepository(
            $ports['gateway']->open(UserIdentityTable::class)->records(),
        );
        $sessionRepository = new AuthSessionRepository(
            $ports['gateway']->open(AuthSessionTable::class)->records(),
        );
        $passwordPolicyService = $this->passwordPolicyService($ports['gateway'], $ports['groups']);

        return new AuthService(
            $ports['accounts'],
            $ports['groups'],
            $identityRepository,
            new AuthSessionRuntime(
                $sessionRepository,
                new AuthCookieIssuer($ports['context'], $ports['settings']),
            ),
            $ports['views'],
            $passwordPolicyService,
        );
    }

    /**
     * Создаёт CLI setup модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return AuthModuleSetup Setup.
     *
     * @throws KernelException Если порт соседа неверного типа.
     */
    public function createSetup(IServiceLocator $serviceLocator): AuthModuleSetup
    {
        $ports = $this->neighborPorts($serviceLocator);

        return new AuthModuleSetup(
            $ports['accounts'],
            $ports['groups'],
            new UserIdentityRepository($ports['gateway']->open(UserIdentityTable::class)->records()),
            new PasswordPolicyRepository($ports['gateway']->open(AuthSecurityPolicyTable::class)->records()),
            new GroupSecurityPolicyRepository($ports['gateway']->open(AuthGroupSecurityPolicyTable::class)->records()),
            $ports['settings'],
        );
    }

    /**
     * Берёт уже собранный AuthService из контейнера модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AuthService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromContainer(IServiceLocator $serviceLocator): AuthService
    {
        $authContainer = $serviceLocator->get(IAuthContainer::class);
        $authService = $authContainer->get(AuthService::class);
        $this->assertPort($authService, AuthService::class);

        return $authService;
    }

    /**
     * Создаёт admin-create.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserCreateService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createUserCreate(IServiceLocator $serviceLocator): UserCreateService
    {
        $ports = $this->neighborPorts($serviceLocator);

        return new UserCreateService(
            $ports['access'],
            $ports['views'],
            $ports['accounts'],
            $ports['groups'],
            new UserIdentityRepository($ports['gateway']->open(UserIdentityTable::class)->records()),
            $this->passwordPolicyService($ports['gateway'], $ports['groups']),
        );
    }

    /**
     * Берёт UserCreateService из контейнера Auth.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserCreateService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function userCreateFromContainer(IServiceLocator $serviceLocator): UserCreateService
    {
        $authContainer = $serviceLocator->get(IAuthContainer::class);
        $userCreateService = $authContainer->get(UserCreateService::class);
        $this->assertPort($userCreateService, UserCreateService::class);

        return $userCreateService;
    }

    /**
     * Создаёт request_bind.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AuthSessionBinder Binder.
     *
     * @throws KernelException Если AuthService неверен.
     */
    public function createSessionBinder(IServiceLocator $serviceLocator): AuthSessionBinder
    {
        return new AuthSessionBinder($this->fromContainer($serviceLocator));
    }

    /**
     * Создаёт сценарий сброса пароля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return PasswordResetService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createPasswordReset(IServiceLocator $serviceLocator): PasswordResetService
    {
        $ports = $this->neighborPorts($serviceLocator);
        $gateway = $ports['gateway'];

        return new PasswordResetService(
            $ports['accounts'],
            new UserIdentityRepository($gateway->open(UserIdentityTable::class)->records()),
            new PasswordResetRepository($gateway->open(AuthPasswordResetTable::class)->records()),
            new AuthSessionRepository($gateway->open(AuthSessionTable::class)->records()),
            $this->passwordPolicyService($gateway, $ports['groups']),
            new MailPasswordResetNotifier($this->mail($serviceLocator), $ports['settings']),
        );
    }

    /**
     * Берёт PasswordResetService из контейнера Auth.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return PasswordResetService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function passwordResetFromContainer(IServiceLocator $serviceLocator): PasswordResetService
    {
        $authContainer = $serviceLocator->get(IAuthContainer::class);
        $passwordResetService = $authContainer->get(PasswordResetService::class);
        $this->assertPort($passwordResetService, PasswordResetService::class);

        return $passwordResetService;
    }

    /**
     * Создаёт сценарий смены пароля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SetPasswordService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createSetPassword(IServiceLocator $serviceLocator): SetPasswordService
    {
        $ports = $this->neighborPorts($serviceLocator);
        $gateway = $ports['gateway'];

        return new SetPasswordService(
            $ports['access'],
            $ports['accounts'],
            new UserIdentityRepository($gateway->open(UserIdentityTable::class)->records()),
            new AuthSessionRepository($gateway->open(AuthSessionTable::class)->records()),
            $this->passwordPolicyService($gateway, $ports['groups']),
            new AuthCookieIssuer($ports['context'], $ports['settings']),
        );
    }

    /**
     * Берёт SetPasswordService из контейнера Auth.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SetPasswordService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function setPasswordFromContainer(IServiceLocator $serviceLocator): SetPasswordService
    {
        $authContainer = $serviceLocator->get(IAuthContainer::class);
        $setPasswordService = $authContainer->get(SetPasswordService::class);
        $this->assertPort($setPasswordService, SetPasswordService::class);

        return $setPasswordService;
    }

    /**
     * Собирает порты соседей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return array{
     *     accounts: IUserAccounts,
     *     groups: IUserGroups,
     *     views: IUserViews,
     *     access: IUserAccess,
     *     gateway: ISmartTableGateway,
     *     context: IRequestContext,
     *     settings: AuthSettings
     * }
     *
     * @throws KernelException Если тип порта неверен.
     */
    private function neighborPorts(IServiceLocator $serviceLocator): array
    {
        $kernelContainer = $serviceLocator->get(IKernelContainer::class);
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $runtimeConfig = $kernelContainer->get(IRuntimeConfig::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        $userViews = $userContainer->get(IUserViews::class);
        $userAccess = $userContainer->get(IUserAccess::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        $this->assertPort($runtimeConfig, IRuntimeConfig::class);
        $this->assertPort($requestContext, IRequestContext::class);
        $this->assertPort($userAccounts, IUserAccounts::class);
        $this->assertPort($userGroups, IUserGroups::class);
        $this->assertPort($userViews, IUserViews::class);
        $this->assertPort($userAccess, IUserAccess::class);
        $this->assertPort($smartTableGateway, ISmartTableGateway::class);

        return [
            'accounts' => $userAccounts,
            'groups' => $userGroups,
            'views' => $userViews,
            'access' => $userAccess,
            'gateway' => $smartTableGateway,
            'context' => $requestContext,
            'settings' => AuthSettings::fromSection($runtimeConfig->section('auth')),
        ];
    }

    /**
     * Политика из карт ST.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IUserGroups $userGroups Группы.
     *
     * @return PasswordPolicyService Сервис.
     */
    private function passwordPolicyService(
        ISmartTableGateway $smartTableGateway,
        IUserGroups $userGroups,
    ): PasswordPolicyService {
        return new PasswordPolicyService(
            new PasswordPolicyRepository($smartTableGateway->open(AuthSecurityPolicyTable::class)->records()),
            new GroupSecurityPolicyRepository(
                $smartTableGateway->open(AuthGroupSecurityPolicyTable::class)->records(),
            ),
            $userGroups,
        );
    }

    /**
     * Фасад Mail.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMail Очередь.
     *
     * @throws KernelException Если тип неверен.
     */
    private function mail(IServiceLocator $serviceLocator): IMail
    {
        $mailContainer = $serviceLocator->get(IMailContainer::class);
        $mail = $mailContainer->get(IMail::class);
        $this->assertPort($mail, IMail::class);

        return $mail;
    }

    /**
     * Проверяет тип порта.
     *
     * @param object $port Экземпляр.
     * @param string $expectedClass Ожидаемый интерфейс.
     *
     * @return void
     *
     * @throws KernelException Если тип неверен.
     */
    private function assertPort(object $port, string $expectedClass): void
    {
        if (is_a($port, $expectedClass)) {
            return;
        }

        throw new KernelException('PORT_TYPE', 'Auth neighbor port has a wrong type');
    }
}
