<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Setup\AuthModuleSetup;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;

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

        return new AuthService(
            $ports['accounts'],
            $ports['groups'],
            $identityRepository,
            $sessionRepository,
            new AuthCookieIssuer($ports['context'], $ports['settings']),
            new AuthUserAssembler($ports['groups']),
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
     * Собирает порты соседей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return array{
     *     accounts: IUserAccounts,
     *     groups: IUserGroups,
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
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        $this->assertPort($runtimeConfig, IRuntimeConfig::class);
        $this->assertPort($requestContext, IRequestContext::class);
        $this->assertPort($userAccounts, IUserAccounts::class);
        $this->assertPort($userGroups, IUserGroups::class);
        $this->assertPort($smartTableGateway, ISmartTableGateway::class);

        return [
            'accounts' => $userAccounts,
            'groups' => $userGroups,
            'gateway' => $smartTableGateway,
            'context' => $requestContext,
            'settings' => AuthSettings::fromSection($runtimeConfig->section('auth')),
        ];
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
